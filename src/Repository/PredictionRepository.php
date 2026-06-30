<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Game;
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

    /**
     * All of a user's predictions on a given game (one per type at most), newest first.
     *
     * @return Prediction[]
     */
    public function findForUserAndGame(User $user, Game $game): array
    {
        return $this->findBy(['user' => $user, 'game' => $game], ['createdAt' => 'DESC']);
    }

    /**
     * RG-02: a user may only have one prediction per game and per type.
     * Returns the existing prediction of that type, if any.
     */
    public function findOneOfTypeForUserAndGame(User $user, Game $game, string $type): ?Prediction
    {
        foreach ($this->findForUserAndGame($user, $game) as $prediction) {
            if ($prediction->getType() === $type) {
                return $prediction;
            }
        }

        return null;
    }
}
