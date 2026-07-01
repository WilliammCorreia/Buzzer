<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Badge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Badge>
 */
class BadgeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Badge::class);
    }

    /**
     * Badges whose threshold is reached with the given amount of points,
     * cheapest first.
     *
     * @return Badge[]
     */
    public function findAwardable(int $points): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.threshold <= :points')
            ->setParameter('points', $points)
            ->orderBy('b.threshold', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
