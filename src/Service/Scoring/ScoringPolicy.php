<?php

declare(strict_types=1);

namespace App\Service\Scoring;

use App\Entity\MatchWinnerPrediction;
use App\Entity\PlayerPropPrediction;
use App\Entity\ScorePrediction;

/**
 * Encapsulates the scoring scale (barème) of the application.
 *
 * RG-04: the scale rewards difficulty.
 *  - match winner  : fixed points if correct;
 *  - exact score   : large bonus if exact, reduced bonus for a good approximation
 *                    (right winner but wrong score);
 *  - player prop   : fixed points if the right side (over/under) was found.
 *
 * Pure logic — no persistence, fully unit-testable.
 */
final class ScoringPolicy
{
    public const POINTS_MATCH_WINNER = 10;
    public const POINTS_SCORE_EXACT = 30;
    public const POINTS_SCORE_RIGHT_WINNER = 5;
    public const POINTS_PLAYER_PROP = 15;

    public function pointsForMatchWinner(MatchWinnerPrediction $prediction): int
    {
        return $prediction->isCorrect() ? self::POINTS_MATCH_WINNER : 0;
    }

    public function pointsForScore(ScorePrediction $prediction): int
    {
        if ($prediction->isExact()) {
            return self::POINTS_SCORE_EXACT;
        }

        if ($prediction->hasCorrectWinner()) {
            return self::POINTS_SCORE_RIGHT_WINNER;
        }

        return 0;
    }

    public function pointsForPlayerProp(PlayerPropPrediction $prediction, float $actualValue): int
    {
        return $prediction->isCorrect($actualValue) ? self::POINTS_PLAYER_PROP : 0;
    }
}
