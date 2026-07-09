<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_user_username', columns: ['username'])]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cette adresse e-mail.')]
#[UniqueEntity(fields: ['username'], message: 'Ce pseudo est déjà utilisé.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /** Roles that may be persisted on a user (ROLE_USER is always granted implicitly). */
    public const AVAILABLE_ROLES = ['ROLE_USER', 'ROLE_MANAGER', 'ROLE_ADMIN'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Merci de saisir une adresse e-mail.')]
    #[Assert\Email(message: "L'adresse « {{ value }} » n'est pas une adresse e-mail valide.")]
    #[Assert\Length(max: 180, maxMessage: "L'adresse e-mail ne peut pas dépasser {{ limit }} caractères.")]
    private ?string $email = null;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    #[Assert\All([new Assert\Choice(choices: self::AVAILABLE_ROLES)])]
    private array $roles = [];

    /**
     * The hashed password.
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Merci de saisir un pseudo.')]
    #[Assert\Length(
        min: 3,
        max: 50,
        minMessage: 'Le pseudo doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le pseudo ne peut pas dépasser {{ limit }} caractères.',
    )]
    private ?string $username = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private bool $isVerified = false;

    /** Favorite teams (owning side of the ManyToMany). */
    /** @var Collection<int, Team> */
    #[ORM\ManyToMany(targetEntity: Team::class, inversedBy: 'favoritedBy')]
    #[ORM\JoinTable(name: 'user_favorite_team')]
    private Collection $favoriteTeams;

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'author', orphanRemoval: true)]
    private Collection $comments;

    /** @var Collection<int, Prediction> */
    #[ORM\OneToMany(targetEntity: Prediction::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $predictions;

    /** @var Collection<int, Notification> */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'recipient', orphanRemoval: true)]
    private Collection $notifications;

    /** @var Collection<int, League> */
    #[ORM\OneToMany(targetEntity: League::class, mappedBy: 'owner')]
    private Collection $ownedLeagues;

    /** @var Collection<int, LeagueMembership> */
    #[ORM\OneToMany(targetEntity: LeagueMembership::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $leagueMemberships;

    /** @var Collection<int, UserBadge> */
    #[ORM\OneToMany(targetEntity: UserBadge::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $userBadges;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->favoriteTeams = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->predictions = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->ownedLeagues = new ArrayCollection();
        $this->leagueMemberships = new ArrayCollection();
        $this->userBadges = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

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

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here.
    }

    /** @return Collection<int, Team> */
    public function getFavoriteTeams(): Collection
    {
        return $this->favoriteTeams;
    }

    public function addFavoriteTeam(Team $team): static
    {
        if (!$this->favoriteTeams->contains($team)) {
            $this->favoriteTeams->add($team);
        }

        return $this;
    }

    public function removeFavoriteTeam(Team $team): static
    {
        $this->favoriteTeams->removeElement($team);

        return $this;
    }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    /** @return Collection<int, Prediction> */
    public function getPredictions(): Collection
    {
        return $this->predictions;
    }

    /** @return Collection<int, Notification> */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    /** @return Collection<int, League> */
    public function getOwnedLeagues(): Collection
    {
        return $this->ownedLeagues;
    }

    /** @return Collection<int, LeagueMembership> */
    public function getLeagueMemberships(): Collection
    {
        return $this->leagueMemberships;
    }

    /** @return Collection<int, UserBadge> */
    public function getUserBadges(): Collection
    {
        return $this->userBadges;
    }

    public function __toString(): string
    {
        return $this->username ?? (string) $this->email;
    }
}
