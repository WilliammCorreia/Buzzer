<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\LeagueRole;
use App\Repository\LeagueMembershipRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Association entity between {@see User} and {@see League} carrying the per-league
 * score, role and join date.
 *
 * RG-07: a user cannot join the same league twice (enforced by the unique constraint).
 */
#[ORM\Entity(repositoryClass: LeagueMembershipRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_membership_user_league', columns: ['user_id', 'league_id'])]
class LeagueMembership
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'leagueMemberships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: League::class, inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?League $league = null;

    #[ORM\Column]
    private int $points = 0;

    #[ORM\Column(length: 20, enumType: LeagueRole::class)]
    private LeagueRole $role = LeagueRole::Member;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $joinedAt;

    public function __construct()
    {
        $this->joinedAt = new \DateTimeImmutable();
    }

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

    public function getLeague(): ?League
    {
        return $this->league;
    }

    public function setLeague(?League $league): static
    {
        $this->league = $league;

        return $this;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): static
    {
        $this->points = $points;

        return $this;
    }

    public function addPoints(int $points): static
    {
        $this->points += $points;

        return $this;
    }

    public function getRole(): LeagueRole
    {
        return $this->role;
    }

    public function setRole(LeagueRole $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getJoinedAt(): \DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function setJoinedAt(\DateTimeImmutable $joinedAt): static
    {
        $this->joinedAt = $joinedAt;

        return $this;
    }
}
