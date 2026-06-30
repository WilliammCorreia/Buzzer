<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Prediction on the exact final score of a game.
 */
#[ORM\Entity]
class ScorePrediction extends Prediction
{
    /** STI child columns must be nullable at the database level. */
    #[ORM\Column(nullable: true)]
    private ?int $predictedHomeScore = null;

    #[ORM\Column(nullable: true)]
    private ?int $predictedAwayScore = null;

    public function getType(): string
    {
        return 'score';
    }

    public function getPredictedHomeScore(): ?int
    {
        return $this->predictedHomeScore;
    }

    public function setPredictedHomeScore(?int $predictedHomeScore): static
    {
        $this->predictedHomeScore = $predictedHomeScore;

        return $this;
    }

    public function getPredictedAwayScore(): ?int
    {
        return $this->predictedAwayScore;
    }

    public function setPredictedAwayScore(?int $predictedAwayScore): static
    {
        $this->predictedAwayScore = $predictedAwayScore;

        return $this;
    }

    /**
     * Exact when both predicted scores match the official final score.
     */
    public function isExact(): bool
    {
        $game = $this->game;

        return $game !== null
            && $game->isFinished()
            && $this->predictedHomeScore === $game->getHomeScore()
            && $this->predictedAwayScore === $game->getAwayScore();
    }

    /**
     * Whether the winning side was correctly called (used for the reduced
     * "good approximation" bonus described in RG-04).
     */
    public function hasCorrectWinner(): bool
    {
        $game = $this->game;
        if ($game === null || !$game->isFinished()
            || $this->predictedHomeScore === null || $this->predictedAwayScore === null
            || $game->getHomeScore() === null || $game->getAwayScore() === null
        ) {
            return false;
        }

        $predictedDiff = $this->predictedHomeScore <=> $this->predictedAwayScore;
        $actualDiff = $game->getHomeScore() <=> $game->getAwayScore();

        return $predictedDiff === $actualDiff;
    }
}
