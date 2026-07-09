<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PredictionStatus;
use App\Repository\PredictionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Root of the prediction hierarchy, mapped with Single Table Inheritance.
 *
 * The concrete type is stored in the discriminator column "type":
 *  - match_winner -> {@see MatchWinnerPrediction}
 *  - score        -> {@see ScorePrediction}
 *  - player_prop  -> {@see PlayerPropPrediction}
 *
 * RG-02: a user may only submit one prediction per game and per type
 * (enforced by the unique constraint on user_id + game_id + type).
 */
#[ORM\Entity(repositoryClass: PredictionRepository::class)]
#[ORM\Table(name: 'prediction')]
#[ORM\UniqueConstraint(name: 'uniq_prediction_user_game_type', columns: ['user_id', 'game_id', 'type'])]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string', length: 30)]
#[ORM\DiscriminatorMap([
    'match_winner' => MatchWinnerPrediction::class,
    'score' => ScorePrediction::class,
    'player_prop' => PlayerPropPrediction::class,
])]
abstract class Prediction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'predictions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    protected ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Game::class, inversedBy: 'predictions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    protected ?Game $game = null;

    #[ORM\Column(length: 20, enumType: PredictionStatus::class)]
    protected PredictionStatus $status = PredictionStatus::Pending;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    protected int $pointsAwarded = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    protected \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Short machine label for the concrete prediction type (matches the
     * discriminator value). Polymorphic hook implemented by each subtype.
     */
    abstract public function getType(): string;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): static
    {
        $this->game = $game;

        return $this;
    }

    public function getStatus(): PredictionStatus
    {
        return $this->status;
    }

    public function setStatus(PredictionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPointsAwarded(): int
    {
        return $this->pointsAwarded;
    }

    public function setPointsAwarded(int $pointsAwarded): static
    {
        $this->pointsAwarded = $pointsAwarded;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === PredictionStatus::Pending;
    }
}
