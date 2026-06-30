<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Game;
use App\Enum\GameStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    /**
     * Paginated calendar: every game with its teams and season eagerly joined
     * (avoids the N+1 problem), most recent first.
     *
     * @return Paginator<Game>
     */
    public function paginateCalendar(int $page = 1, int $perPage = 15): Paginator
    {
        $page = max(1, $page);

        $query = $this->createQueryBuilder('g')
            ->addSelect('home', 'away', 'season')
            ->innerJoin('g.homeTeam', 'home')
            ->innerJoin('g.awayTeam', 'away')
            ->leftJoin('g.season', 'season')
            ->orderBy('g.startsAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery();

        // fetchJoinCollection: false — we only fetch-join to-one relations here.
        return new Paginator($query, fetchJoinCollection: false);
    }
}
