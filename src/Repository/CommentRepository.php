<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * Comments of a game with their author joined (avoids the N+1 problem),
     * newest first.
     *
     * @return Comment[]
     */
    public function findForGameWithAuthor(Game $game): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('author')
            ->innerJoin('c.author', 'author')
            ->andWhere('c.game = :game')
            ->andWhere('c.isHidden = false')
            ->setParameter('game', $game)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * All comments (including hidden ones), with author and game joined (avoids
     * the N+1 problem), for the moderation queue.
     *
     * @return Comment[]
     */
    public function findAllForModeration(): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('author', 'game')
            ->innerJoin('c.author', 'author')
            ->innerJoin('c.game', 'game')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
