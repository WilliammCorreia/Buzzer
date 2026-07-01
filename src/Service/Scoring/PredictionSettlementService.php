<?php

declare(strict_types=1);

namespace App\Service\Scoring;

use App\Entity\Game;
use App\Entity\MatchWinnerPrediction;
use App\Entity\PlayerPropPrediction;
use App\Entity\Prediction;
use App\Entity\ScorePrediction;
use App\Entity\User;
use App\Enum\PredictionStatus;
use App\Service\Gamification\BadgeAwarderInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Settles all pending predictions of a finished game (UC-52).
 *
 * RG-03: only runs on a FINISHED game with an official score, and is idempotent —
 *        already-settled predictions are skipped, so re-running never double-credits.
 * RG-04: applies the {@see ScoringPolicy} scale.
 * RG-05: winning points are propagated to the author's standing in each of their
 *        leagues.
 * UC-54: winners have their eligible badges attributed after settlement.
 */
final class PredictionSettlementService
{
    public function __construct(
        private readonly ScoringPolicy $policy,
        private readonly EntityManagerInterface $entityManager,
        private readonly BadgeAwarderInterface $badgeAwarder,
    ) {
    }

    /**
     * @param array<int, array<string, float>> $playerStats Actual box-score values
     *        keyed by player API id, then by stat type value, e.g.
     *        [2544 => ['POINTS' => 31.0, 'REBOUNDS' => 8.0]]. Required to settle
     *        player-prop predictions; missing entries leave them pending.
     */
    public function settleGame(Game $game, array $playerStats = []): SettlementReport
    {
        if (!$game->isFinished() || $game->getHomeScore() === null || $game->getAwayScore() === null) {
            throw new \InvalidArgumentException('A game must be finished with an official score before settlement (RG-03).');
        }

        $report = new SettlementReport();

        /** @var array<int, User> $winners */
        $winners = [];

        foreach ($game->getPredictions() as $prediction) {
            if (!$prediction->isPending()) {
                // Idempotent: a prediction is settled at most once (RG-03).
                ++$report->skipped;
                continue;
            }

            $points = $this->evaluate($prediction, $playerStats);
            if ($points === null) {
                // Not settleable yet (e.g. player prop without box-score stats).
                ++$report->skipped;
                continue;
            }

            $prediction->setPointsAwarded($points);

            if ($points > 0) {
                $prediction->setStatus(PredictionStatus::Won);
                $this->creditLeagues($prediction, $points);
                ++$report->won;
                $user = $prediction->getUser();
                if ($user !== null) {
                    $winners[spl_object_id($user)] = $user;
                }
            } else {
                $prediction->setStatus(PredictionStatus::Lost);
                ++$report->lost;
            }
        }

        $this->entityManager->flush();

        // UC-54: attribute newly eligible badges to the winners.
        foreach ($winners as $user) {
            $report->badgesAwarded += $this->badgeAwarder->awardFor($user);
        }

        return $report;
    }

    /**
     * @param array<int, array<string, float>> $playerStats
     */
    private function evaluate(Prediction $prediction, array $playerStats): ?int
    {
        return match (true) {
            $prediction instanceof MatchWinnerPrediction => $this->policy->pointsForMatchWinner($prediction),
            $prediction instanceof ScorePrediction => $this->policy->pointsForScore($prediction),
            $prediction instanceof PlayerPropPrediction => $this->evaluatePlayerProp($prediction, $playerStats),
            default => null,
        };
    }

    /**
     * @param array<int, array<string, float>> $playerStats
     */
    private function evaluatePlayerProp(PlayerPropPrediction $prediction, array $playerStats): ?int
    {
        $player = $prediction->getPlayer();
        $statType = $prediction->getStatType();
        if ($player === null || $statType === null) {
            return null;
        }

        $actual = $playerStats[$player->getApiId()][$statType->value] ?? null;
        if ($actual === null) {
            return null;
        }

        return $this->policy->pointsForPlayerProp($prediction, (float) $actual);
    }

    private function creditLeagues(Prediction $prediction, int $points): void
    {
        $user = $prediction->getUser();
        if ($user === null) {
            return;
        }

        // RG-05: repercute the winning points on the author's standing in each league.
        foreach ($user->getLeagueMemberships() as $membership) {
            $membership->addPoints($points);
        }
    }
}
