<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Prediction on the winning team of a game.
 */
#[ORM\Entity]
class MatchWinnerPrediction extends Prediction
{
    /** STI child columns must be nullable at the database level. */
    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(name: 'predicted_winner_id', nullable: true, onDelete: 'CASCADE')]
    private ?Team $predictedWinner = null;

    public function getType(): string
    {
        return 'match_winner';
    }

    public function getPredictedWinner(): ?Team
    {
        return $this->predictedWinner;
    }

    public function setPredictedWinner(?Team $predictedWinner): static
    {
        $this->predictedWinner = $predictedWinner;

        return $this;
    }

    /**
     * Correct when the predicted team is the actual winner of a settled game.
     */
    public function isCorrect(): bool
    {
        $winner = $this->game?->getWinner();

        return $winner !== null
            && $this->predictedWinner !== null
            && $winner === $this->predictedWinner;
    }
}
