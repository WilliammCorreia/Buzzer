<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Prediction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Prediction>
 */
class PredictionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prediction::class);
    }

    /**
     * A user's prediction history with the related game and teams joined to
     * avoid the N+1 problem.
     *
     * @return Prediction[]
     */
    public function findHistoryForUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('g', 'home', 'away')
            ->innerJoin('p.game', 'g')
            ->innerJoin('g.homeTeam', 'home')
            ->innerJoin('g.awayTeam', 'away')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
