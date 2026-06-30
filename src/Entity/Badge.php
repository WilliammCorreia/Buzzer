<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BadgeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BadgeRepository::class)]
class Badge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $iconUrl = null;

    /** Numeric threshold that, once reached, automatically awards the badge. */
    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private ?int $threshold = null;

    /** @var Collection<int, UserBadge> */
    #[ORM\OneToMany(targetEntity: UserBadge::class, mappedBy: 'badge', orphanRemoval: true)]
    private Collection $userBadges;

    public function __construct()
    {
        $this->userBadges = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getIconUrl(): ?string
    {
        return $this->iconUrl;
    }

    public function setIconUrl(?string $iconUrl): static
    {
        $this->iconUrl = $iconUrl;

        return $this;
    }

    public function getThreshold(): ?int
    {
        return $this->threshold;
    }

    public function setThreshold(int $threshold): static
    {
        $this->threshold = $threshold;

        return $this;
    }

    /** @return Collection<int, UserBadge> */
    public function getUserBadges(): Collection
    {
        return $this->userBadges;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
