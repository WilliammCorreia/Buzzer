<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Game;
use App\Enum\GameStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /**
     * Upcoming games with their teams eagerly joined to avoid the N+1 problem
     * when rendering the calendar.
     *
     * @return Game[]
     */
    public function findUpcomingWithTeams(int $limit = 20): array
    {
        return $this->createQueryBuilder('g')
            ->addSelect('home', 'away')
            ->innerJoin('g.homeTeam', 'home')
            ->innerJoin('g.awayTeam', 'away')
            ->andWhere('g.status = :status')
            ->setParameter('status', GameStatus::Scheduled)
            ->orderBy('g.startsAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
