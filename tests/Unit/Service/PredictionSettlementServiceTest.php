<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Game;
use App\Entity\League;
use App\Entity\LeagueMembership;
use App\Entity\MatchWinnerPrediction;
use App\Entity\Player;
use App\Entity\PlayerPropPrediction;
use App\Entity\ScorePrediction;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\Comparison;
use App\Enum\GameStatus;
use App\Enum\PredictionStatus;
use App\Enum\StatType;
use App\Service\Scoring\PredictionSettlementService;
use App\Service\Scoring\ScoringPolicy;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class PredictionSettlementServiceTest extends TestCase
{
    private function service(): PredictionSettlementService
    {
        return new PredictionSettlementService(
            new ScoringPolicy(),
            // A stub is enough: the EM is only asked to flush(), with nothing to verify.
            $this->createStub(EntityManagerInterface::class),
        );
    }

    private function team(int $id, string $name): Team
    {
        return (new Team())->setApiId($id)->setName($name)->setCode(substr($name, 0, 3))->setCity($name);
    }

    private function finishedGame(int $home, int $away): Game
    {
        return (new Game())
            ->setApiId(10)
            ->setHomeTeam($this->team(1, 'Home'))
            ->setAwayTeam($this->team(2, 'Away'))
            ->setStartsAt(new \DateTimeImmutable('-1 day'))
            ->setStatus(GameStatus::Finished)
            ->setHomeScore($home)
            ->setAwayScore($away);
    }

    /**
     * @return array{0: User, 1: LeagueMembership}
     */
    private function userWithLeague(): array
    {
        $user = (new User())->setEmail('parieur@buzzer.test')->setUsername('parieur');
        $league = (new League())->setName('Ligue')->setOwner($user)->setInviteCode('CODE1234');
        $membership = (new LeagueMembership())->setUser($user)->setLeague($league)->setPoints(0);
        $user->getLeagueMemberships()->add($membership);

        return [$user, $membership];
    }

    public function testSettlesPendingPredictionsAndCreditsLeagueStandings(): void
    {
        $game = $this->finishedGame(110, 100);
        [$user, $membership] = $this->userWithLeague();

        $winner = new MatchWinnerPrediction();
        $winner->setUser($user)->setPredictedWinner($game->getHomeTeam());
        $game->addPrediction($winner);

        $score = (new ScorePrediction())->setPredictedHomeScore(110)->setPredictedAwayScore(100);
        $score->setUser($user);
        $game->addPrediction($score);

        $report = $this->service()->settleGame($game);

        self::assertSame(PredictionStatus::Won, $winner->getStatus());
        self::assertSame(ScoringPolicy::POINTS_MATCH_WINNER, $winner->getPointsAwarded());
        self::assertSame(PredictionStatus::Won, $score->getStatus());
        self::assertSame(ScoringPolicy::POINTS_SCORE_EXACT, $score->getPointsAwarded());

        // RG-05: 10 + 30 credited to the user's standing in their league.
        self::assertSame(40, $membership->getPoints());
        self::assertSame(2, $report->won);
        self::assertSame(0, $report->lost);
    }

    public function testSettlementIsIdempotent(): void
    {
        $game = $this->finishedGame(110, 100);
        [$user, $membership] = $this->userWithLeague();

        $winner = new MatchWinnerPrediction();
        $winner->setUser($user)->setPredictedWinner($game->getHomeTeam());
        $game->addPrediction($winner);

        $this->service()->settleGame($game);
        self::assertSame(10, $membership->getPoints());

        // RG-03: running it again must not double-credit.
        $report = $this->service()->settleGame($game);
        self::assertSame(10, $membership->getPoints());
        self::assertSame(0, $report->won);
        self::assertSame(1, $report->skipped);
    }

    public function testThrowsWhenGameNotFinished(): void
    {
        $game = (new Game())
            ->setApiId(11)
            ->setHomeTeam($this->team(1, 'Home'))
            ->setAwayTeam($this->team(2, 'Away'))
            ->setStartsAt(new \DateTimeImmutable('+1 day'))
            ->setStatus(GameStatus::Scheduled);

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->settleGame($game);
    }

    public function testPlayerPropStaysPendingUntilStatsAreAvailable(): void
    {
        $game = $this->finishedGame(110, 100);
        $player = (new Player())->setApiId(2544)->setFirstName('LeBron')->setLastName('James');

        $prop = (new PlayerPropPrediction())
            ->setPlayer($player)
            ->setStatType(StatType::Points)
            ->setPredictedValue(25.5)
            ->setComparison(Comparison::Over);
        [$user] = $this->userWithLeague();
        $prop->setUser($user);
        $game->addPrediction($prop);

        // No box-score stats yet -> left pending.
        $report = $this->service()->settleGame($game);
        self::assertSame(PredictionStatus::Pending, $prop->getStatus());
        self::assertSame(1, $report->skipped);

        // Stats arrive (player api id 2544 scored 31) -> settled as won.
        $report = $this->service()->settleGame($game, [2544 => ['POINTS' => 31.0]]);
        self::assertSame(PredictionStatus::Won, $prop->getStatus());
        self::assertSame(ScoringPolicy::POINTS_PLAYER_PROP, $prop->getPointsAwarded());
        self::assertSame(1, $report->won);
    }
}
