<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Comparison;
use App\Enum\StatType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Prediction on a player statistic (points / rebounds / assists) being over or
 * under a given threshold.
 */
#[ORM\Entity]
class PlayerPropPrediction extends Prediction
{
    /** STI child columns must be nullable at the database level. */
    #[ORM\ManyToOne(targetEntity: Player::class)]
    #[ORM\JoinColumn(name: 'player_id', nullable: true, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Veuillez choisir un joueur.')]
    private ?Player $player = null;

    #[ORM\Column(length: 20, nullable: true, enumType: StatType::class)]
    #[Assert\NotNull(message: 'Veuillez choisir une statistique.')]
    private ?StatType $statType = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Assert\NotNull(message: 'Veuillez saisir un seuil.')]
    #[Assert\PositiveOrZero(message: 'Le seuil doit être supérieur ou égal à 0.')]
    #[Assert\LessThanOrEqual(value: 200, message: 'Le seuil ne peut pas dépasser {{ compared_value }}.')]
    private ?float $predictedValue = null;

    #[ORM\Column(length: 10, nullable: true, enumType: Comparison::class)]
    #[Assert\NotNull(message: 'Veuillez choisir le sens du pronostic (au-dessus ou en dessous).')]
    private ?Comparison $comparison = null;

    public function getType(): string
    {
        return 'player_prop';
    }

    public function getPlayer(): ?Player
    {
        return $this->player;
    }

    public function setPlayer(?Player $player): static
    {
        $this->player = $player;

        return $this;
    }

    public function getStatType(): ?StatType
    {
        return $this->statType;
    }

    public function setStatType(?StatType $statType): static
    {
        $this->statType = $statType;

        return $this;
    }

    public function getPredictedValue(): ?float
    {
        return $this->predictedValue;
    }

    public function setPredictedValue(?float $predictedValue): static
    {
        $this->predictedValue = $predictedValue;

        return $this;
    }

    public function getComparison(): ?Comparison
    {
        return $this->comparison;
    }

    public function setComparison(?Comparison $comparison): static
    {
        $this->comparison = $comparison;

        return $this;
    }

    /**
     * Correct when the player's actual statistic falls on the predicted side of
     * the threshold. The actual value comes from the box score fetched at
     * settlement time, so it is supplied by the caller rather than stored.
     */
    public function isCorrect(float $actualValue): bool
    {
        if ($this->predictedValue === null || $this->comparison === null) {
            return false;
        }

        return match ($this->comparison) {
            Comparison::Over => $actualValue > $this->predictedValue,
            Comparison::Under => $actualValue < $this->predictedValue,
        };
    }
}
