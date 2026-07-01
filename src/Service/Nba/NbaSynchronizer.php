<?php

declare(strict_types=1);

namespace App\Service\Nba;

use App\Entity\Game;
use App\Entity\Player;
use App\Entity\Season;
use App\Entity\Team;
use App\Enum\GameStatus;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Repository\SeasonRepository;
use App\Repository\TeamRepository;
use App\Service\Scoring\PredictionSettlementService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Idempotent upsert of the NBA catalog (teams, players, games) from the API
 * into the local database (RG-12). Entities are matched by their API id, so
 * re-running the sync never creates duplicates (UC-50, RG-12).
 *
 * When a game transitions to FINISHED with an official score, the settlement
 * of its pending predictions is triggered (UC-51 -> UC-52).
 */
final class NbaSynchronizer
{
    /** @var array<int, Season> */
    private array $seasonCache = [];

    public function __construct(
        private readonly NbaApiClient $api,
        private readonly EntityManagerInterface $entityManager,
        private readonly TeamRepository $teams,
        private readonly PlayerRepository $players,
        private readonly GameRepository $games,
        private readonly SeasonRepository $seasons,
        private readonly PredictionSettlementService $settlement,
    ) {
    }

    public function syncTeams(): SyncReport
    {
        $report = new SyncReport();

        foreach ($this->api->getTeams() as $raw) {
            // Skip All-Star / special teams: only real franchises are kept.
            if ($this->dig($raw, 'nbaFranchise') !== true) {
                ++$report->skipped;
                continue;
            }

            $team = $this->teams->findOneBy(['apiId' => $this->int($this->dig($raw, 'id'))]);
            $isNew = $team === null;
            $team ??= new Team();

            $team
                ->setApiId($this->int($this->dig($raw, 'id')))
                ->setName($this->str($this->dig($raw, 'name')))
                ->setCode($this->str($this->dig($raw, 'code')))
                ->setCity($this->str($this->dig($raw, 'city')))
                ->setConference($this->nullableStr($this->dig($raw, 'leagues', 'standard', 'conference')))
                ->setDivision($this->nullableStr($this->dig($raw, 'leagues', 'standard', 'division')))
                ->setLogoUrl($this->nullableStr($this->dig($raw, 'logo')));

            $this->entityManager->persist($team);
            $isNew ? ++$report->created : ++$report->updated;
        }

        $this->entityManager->flush();

        return $report;
    }

    public function syncPlayers(int $season): SyncReport
    {
        $report = new SyncReport();

        foreach ($this->teams->findAll() as $team) {
            $apiId = $team->getApiId();
            if ($apiId === null) {
                continue;
            }

            foreach ($this->api->getPlayers($apiId, $season) as $raw) {
                $player = $this->players->findOneBy(['apiId' => $this->int($this->dig($raw, 'id'))]);
                $isNew = $player === null;
                $player ??= new Player();

                $player
                    ->setApiId($this->int($this->dig($raw, 'id')))
                    ->setFirstName($this->str($this->dig($raw, 'firstname')))
                    ->setLastName($this->str($this->dig($raw, 'lastname')))
                    ->setPosition($this->nullableStr($this->dig($raw, 'leagues', 'standard', 'pos')))
                    ->setTeam($team);

                $this->entityManager->persist($player);
                $isNew ? ++$report->created : ++$report->updated;
            }
        }

        $this->entityManager->flush();

        return $report;
    }

    public function syncGames(int $season, ?string $date = null): SyncReport
    {
        $report = new SyncReport();
        $rawGames = $date !== null ? $this->api->getGamesByDate($date) : $this->api->getGamesBySeason($season);

        /** @var list<Game> $newlyFinished */
        $newlyFinished = [];

        foreach ($rawGames as $raw) {
            $home = $this->teams->findOneBy(['apiId' => $this->int($this->dig($raw, 'teams', 'home', 'id'))]);
            $away = $this->teams->findOneBy(['apiId' => $this->int($this->dig($raw, 'teams', 'visitors', 'id'))]);
            if ($home === null || $away === null) {
                // A team we don't know yet (sync teams first): skip this game.
                ++$report->skipped;
                continue;
            }

            $game = $this->games->findOneBy(['apiId' => $this->int($this->dig($raw, 'id'))]);
            $isNew = $game === null;
            $previousStatus = $game?->getStatus();
            $game ??= new Game();

            $start = $this->nullableStr($this->dig($raw, 'date', 'start'));
            $status = $this->mapStatus(
                $this->int($this->dig($raw, 'status', 'short')),
                $this->nullableStr($this->dig($raw, 'status', 'long')),
            );

            $game
                ->setApiId($this->int($this->dig($raw, 'id')))
                ->setHomeTeam($home)
                ->setAwayTeam($away)
                ->setSeason($this->resolveSeason($this->int($this->dig($raw, 'season')) ?: $season))
                ->setStartsAt($start !== null ? new \DateTimeImmutable($start) : new \DateTimeImmutable())
                ->setStatus($status)
                ->setHomeScore($this->nullableInt($this->dig($raw, 'scores', 'home', 'points')))
                ->setAwayScore($this->nullableInt($this->dig($raw, 'scores', 'visitors', 'points')));

            $this->entityManager->persist($game);
            $isNew ? ++$report->created : ++$report->updated;

            if ($status === GameStatus::Finished && $previousStatus !== GameStatus::Finished
                && $game->getHomeScore() !== null && $game->getAwayScore() !== null
            ) {
                $newlyFinished[] = $game;
            }
        }

        $this->entityManager->flush();

        // UC-52: settle predictions of games that just finished.
        foreach ($newlyFinished as $game) {
            $report->settled += $this->settlement->settleGame($game)->settled();
        }

        return $report;
    }

    private function resolveSeason(int $year): Season
    {
        if (isset($this->seasonCache[$year])) {
            return $this->seasonCache[$year];
        }

        $season = $this->seasons->findOneBy(['year' => $year]);
        if ($season === null) {
            $season = (new Season())
                ->setYear($year)
                ->setLabel(sprintf('Saison %d-%d', $year, ($year + 1) % 100))
                ->setStartDate(new \DateTimeImmutable(sprintf('%d-10-01', $year)))
                ->setEndDate(new \DateTimeImmutable(sprintf('%d-06-30', $year + 1)));
            $this->entityManager->persist($season);
        }

        return $this->seasonCache[$year] = $season;
    }

    private function mapStatus(int $short, ?string $long): GameStatus
    {
        $long = strtolower($long ?? '');
        if (str_contains($long, 'cancel') || str_contains($long, 'postpon')) {
            return GameStatus::Cancelled;
        }

        return match ($short) {
            2 => GameStatus::Live,
            3 => GameStatus::Finished,
            default => GameStatus::Scheduled,
        };
    }

    /**
     * Safely navigate a decoded JSON structure.
     *
     * @param array<string, mixed> $data
     */
    private function dig(array $data, string ...$keys): mixed
    {
        $value = $data;
        foreach ($keys as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    private function str(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    private function nullableStr(mixed $value): ?string
    {
        return \is_scalar($value) ? (string) $value : null;
    }

    private function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
