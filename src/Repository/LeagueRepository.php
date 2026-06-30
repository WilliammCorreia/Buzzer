<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\League;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<League>
 */
class LeagueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, League::class);
    }

    public function findOneByInviteCode(string $inviteCode): ?League
    {
        return $this->findOneBy(['inviteCode' => $inviteCode]);
    }
}
