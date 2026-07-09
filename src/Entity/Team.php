<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TeamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
#[UniqueEntity(fields: ['apiId'])]
class Team
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['team:read'])]
    private ?int $id = null;

    /** Identifier of the team in the upstream NBA API (used for idempotent sync). */
    #[ORM\Column(unique: true)]
    #[Assert\NotNull]
    #[Assert\Positive]
    private ?int $apiId = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['team:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 10)]
    #[Groups(['team:read'])]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['team:read'])]
    private ?string $city = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $conference = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $division = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Assert\Url(requireTld: true)]
    #[Groups(['team:read'])]
    private ?string $logoUrl = null;

    /** @var Collection<int, Player> */
    #[ORM\OneToMany(targetEntity: Player::class, mappedBy: 'team')]
    private Collection $players;

    /** @var Collection<int, Game> */
    #[ORM\OneToMany(targetEntity: Game::class, mappedBy: 'homeTeam')]
    private Collection $homeGames;

    /** @var Collection<int, Game> */
    #[ORM\OneToMany(targetEntity: Game::class, mappedBy: 'awayTeam')]
    private Collection $awayGames;

    /** Users that flagged this team as a favorite (inverse side of the ManyToMany). */
    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'favoriteTeams')]
    private Collection $favoritedBy;

    public function __construct()
    {
        $this->players = new ArrayCollection();
        $this->homeGames = new ArrayCollection();
        $this->awayGames = new ArrayCollection();
        $this->favoritedBy = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getConference(): ?string
    {
        return $this->conference;
    }

    public function setConference(?string $conference): static
    {
        $this->conference = $conference;

        return $this;
    }

    public function getDivision(): ?string
    {
        return $this->division;
    }

    public function setDivision(?string $division): static
    {
        $this->division = $division;

        return $this;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(?string $logoUrl): static
    {
        $this->logoUrl = $logoUrl;

        return $this;
    }

    /** @return Collection<int, Player> */
    public function getPlayers(): Collection
    {
        return $this->players;
    }

    public function addPlayer(Player $player): static
    {
        if (!$this->players->contains($player)) {
            $this->players->add($player);
            $player->setTeam($this);
        }

        return $this;
    }

    public function removePlayer(Player $player): static
    {
        if ($this->players->removeElement($player) && $player->getTeam() === $this) {
            $player->setTeam(null);
        }

        return $this;
    }

    /** @return Collection<int, Game> */
    public function getHomeGames(): Collection
    {
        return $this->homeGames;
    }

    /** @return Collection<int, Game> */
    public function getAwayGames(): Collection
    {
        return $this->awayGames;
    }

    /** @return Collection<int, User> */
    public function getFavoritedBy(): Collection
    {
        return $this->favoritedBy;
    }

    public function addFavoritedBy(User $user): static
    {
        if (!$this->favoritedBy->contains($user)) {
            $this->favoritedBy->add($user);
            $user->addFavoriteTeam($this);
        }

        return $this;
    }

    public function removeFavoritedBy(User $user): static
    {
        if ($this->favoritedBy->removeElement($user)) {
            $user->removeFavoriteTeam($this);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
