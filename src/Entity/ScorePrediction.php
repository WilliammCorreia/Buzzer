<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Prediction on the exact final score of a game.
 */
#[ORM\Entity]
class ScorePrediction extends Prediction
{
    /** STI child columns must be nullable at the database level. */
    #[ORM\Column(nullable: true)]
    #[Assert\NotNull(message: "Le score de l'équipe à domicile est obligatoire.")]
    #[Assert\Range(min: 0, max: 300, notInRangeMessage: 'Le score domicile doit être compris entre {{ min }} et {{ max }} points.')]
    private ?int $predictedHomeScore = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotNull(message: "Le score de l'équipe à l'extérieur est obligatoire.")]
    #[Assert\Range(min: 0, max: 300, notInRangeMessage: 'Le score extérieur doit être compris entre {{ min }} et {{ max }} points.')]
    private ?int $predictedAwayScore = null;

    public function getType(): string
    {
        return 'score';
    }

    /** A NBA game cannot end in a draw, so neither can a prediction about its score. */
    #[Assert\Callback]
    public function validateNoDraw(ExecutionContextInterface $context): void
    {
        if ($this->predictedHomeScore === null || $this->predictedAwayScore === null) {
            return;
        }

        if ($this->predictedHomeScore === $this->predictedAwayScore) {
            $context->buildViolation('Un match NBA ne peut pas se terminer sur une égalité : les deux scores doivent être différents.')
                ->atPath('predictedAwayScore')
                ->addViolation();
        }
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
