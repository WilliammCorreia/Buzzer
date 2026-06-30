<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LeagueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LeagueRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_league_invite_code', columns: ['invite_code'])]
#[UniqueEntity(fields: ['inviteCode'])]
class League
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 100)]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'ownedLeagues')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    /** RG-06: each league exposes a unique invitation code. */
    #[ORM\Column(length: 16)]
    #[Assert\NotBlank]
    private ?string $inviteCode = null;

    #[ORM\Column]
    private bool $isPrivate = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, LeagueMembership> */
    #[ORM\OneToMany(targetEntity: LeagueMembership::class, mappedBy: 'league', orphanRemoval: true)]
    private Collection $memberships;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->memberships = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getInviteCode(): ?string
    {
        return $this->inviteCode;
    }

    public function setInviteCode(string $inviteCode): static
    {
        $this->inviteCode = $inviteCode;

        return $this;
    }

    public function isPrivate(): bool
    {
        return $this->isPrivate;
    }

    public function setIsPrivate(bool $isPrivate): static
    {
        $this->isPrivate = $isPrivate;

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

    /** @return Collection<int, LeagueMembership> */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMembership(LeagueMembership $membership): static
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
            $membership->setLeague($this);
        }

        return $this;
    }

    public function removeMembership(LeagueMembership $membership): static
    {
        if ($this->memberships->removeElement($membership) && $membership->getLeague() === $this) {
            $membership->setLeague(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
