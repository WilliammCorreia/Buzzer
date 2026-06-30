<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\League;
use App\Entity\LeagueMembership;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LeagueMembership>
 */
class LeagueMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeagueMembership::class);
    }

    /**
     * Leaderboard of a league: memberships ordered by points, with the member
     * user joined to avoid the N+1 problem.
     *
     * @return LeagueMembership[]
     */
    public function findLeaderboard(League $league): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('u')
            ->innerJoin('m.user', 'u')
            ->andWhere('m.league = :league')
            ->setParameter('league', $league)
            ->orderBy('m.points', 'DESC')
            ->addOrderBy('m.joinedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
