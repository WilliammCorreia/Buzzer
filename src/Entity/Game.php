<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\GameStatus;
use App\Repository\GameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\Index(name: 'idx_game_starts_at', columns: ['starts_at'])]
class Game
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['game:list'])]
    private ?int $id = null;

    /** Identifier of the game in the upstream NBA API (used for idempotent sync). */
    #[ORM\Column(unique: true)]
    #[Assert\NotNull]
    private ?int $apiId = null;

    #[ORM\ManyToOne(targetEntity: Team::class, inversedBy: 'homeGames')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['game:list'])]
    private ?Team $homeTeam = null;

    #[ORM\ManyToOne(targetEntity: Team::class, inversedBy: 'awayGames')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['game:list'])]
    private ?Team $awayTeam = null;

    #[ORM\ManyToOne(targetEntity: Season::class, inversedBy: 'games')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['game:detail'])]
    private ?Season $season = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotNull]
    #[Groups(['game:list'])]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(length: 20, enumType: GameStatus::class)]
    #[Groups(['game:list'])]
    private GameStatus $status = GameStatus::Scheduled;

    #[ORM\Column(nullable: true)]
    #[Groups(['game:list'])]
    private ?int $homeScore = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['game:list'])]
    private ?int $awayScore = null;

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'game', orphanRemoval: true)]
    private Collection $comments;

    /** @var Collection<int, Prediction> */
    #[ORM\OneToMany(targetEntity: Prediction::class, mappedBy: 'game', orphanRemoval: true)]
    private Collection $predictions;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->predictions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getApiId(): ?int
    {
        return $this->apiId;
    }

    public function setApiId(int $apiId): static
    {
        $this->apiId = $apiId;

        return $this;
    }

    public function getHomeTeam(): ?Team
    {
        return $this->homeTeam;
    }

    public function setHomeTeam(?Team $homeTeam): static
    {
        $this->homeTeam = $homeTeam;

        return $this;
    }

    public function getAwayTeam(): ?Team
    {
        return $this->awayTeam;
    }

    public function setAwayTeam(?Team $awayTeam): static
    {
        $this->awayTeam = $awayTeam;

        return $this;
    }

    public function getSeason(): ?Season
    {
        return $this->season;
    }

    public function setSeason(?Season $season): static
    {
        $this->season = $season;

        return $this;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getStatus(): GameStatus
    {
        return $this->status;
    }

    public function setStatus(GameStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getHomeScore(): ?int
    {
        return $this->homeScore;
    }

    public function setHomeScore(?int $homeScore): static
    {
        $this->homeScore = $homeScore;

        return $this;
    }

    public function getAwayScore(): ?int
    {
        return $this->awayScore;
    }

    public function setAwayScore(?int $awayScore): static
    {
        $this->awayScore = $awayScore;

        return $this;
    }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setGame($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment) && $comment->getGame() === $this) {
            $comment->setGame(null);
        }

        return $this;
    }

    /** @return Collection<int, Prediction> */
    public function getPredictions(): Collection
    {
        return $this->predictions;
    }

    public function addPrediction(Prediction $prediction): static
    {
        if (!$this->predictions->contains($prediction)) {
            $this->predictions->add($prediction);
            $prediction->setGame($this);
        }

        return $this;
    }

    public function removePrediction(Prediction $prediction): static
    {
        if ($this->predictions->removeElement($prediction) && $prediction->getGame() === $this) {
            $prediction->setGame(null);
        }

        return $this;
    }

    /**
     * RG-01: predictions are accepted only while the game is scheduled and the
     * tip-off has not happened yet.
     *
     * Exposed as the virtual `openForPredictions` property in the API.
     */
    #[Groups(['game:list'])]
    public function isOpenForPredictions(): bool
    {
        return $this->status->isOpenForPredictions()
            && $this->startsAt instanceof \DateTimeImmutable
            && $this->startsAt > new \DateTimeImmutable();
    }

    public function isFinished(): bool
    {
        return $this->status === GameStatus::Finished;
    }

    /**
     * Winning team once the game is settled, or null for a tie / unsettled game.
     */
    public function getWinner(): ?Team
    {
        if (!$this->isFinished() || $this->homeScore === null || $this->awayScore === null) {
            return null;
        }

        if ($this->homeScore === $this->awayScore) {
            return null;
        }

        return $this->homeScore > $this->awayScore ? $this->homeTeam : $this->awayTeam;
    }

    public function __toString(): string
    {
        return sprintf('%s @ %s', (string) $this->awayTeam, (string) $this->homeTeam);
    }
}
