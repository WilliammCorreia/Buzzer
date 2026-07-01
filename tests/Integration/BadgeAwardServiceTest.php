<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Badge;
use App\Entity\Game;
use App\Entity\MatchWinnerPrediction;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\GameStatus;
use App\Enum\PredictionStatus;
use App\Repository\NotificationRepository;
use App\Repository\UserBadgeRepository;
use App\Service\Gamification\BadgeAwardService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BadgeAwardServiceTest extends KernelTestCase
{
    public function testAwardsReachedBadgesOnceAndNotifies(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $em = $container->get(EntityManagerInterface::class);
        $service = $container->get(BadgeAwardService::class);
        $userBadges = $container->get(UserBadgeRepository::class);
        $notifications = $container->get(NotificationRepository::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(BadgeAwardService::class, $service);
        self::assertInstanceOf(UserBadgeRepository::class, $userBadges);
        self::assertInstanceOf(NotificationRepository::class, $notifications);

        $suffix = uniqid();
        $user = (new User())->setEmail("badge_$suffix@buzzer.test")->setUsername('b'.substr($suffix, -6))->setPassword('x');
        $home = (new Team())->setApiId(random_int(1, 2_000_000_000))->setName('H')->setCode('H')->setCity('H');
        $away = (new Team())->setApiId(random_int(1, 2_000_000_000))->setName('A')->setCode('A')->setCity('A');
        $game = (new Game())
            ->setApiId(random_int(1, 2_000_000_000))
            ->setHomeTeam($home)->setAwayTeam($away)
            ->setStartsAt(new \DateTimeImmutable('-1 day'))
            ->setStatus(GameStatus::Finished)->setHomeScore(110)->setAwayScore(100);

        // A won prediction worth 40 points for this user.
        $prediction = (new MatchWinnerPrediction())->setPredictedWinner($home);
        $prediction->setUser($user)->setGame($game)->setPointsAwarded(40)->setStatus(PredictionStatus::Won);

        $low = (new Badge())->setName("Palier bas $suffix")->setDescription('d')->setThreshold(10);
        $high = (new Badge())->setName("Palier haut $suffix")->setDescription('d')->setThreshold(100);

        foreach ([$user, $home, $away, $game, $prediction, $low, $high] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        // 40 points -> the 10-threshold badge is granted, the 100 one is not.
        self::assertSame(1, $service->awardFor($user));
        // RG-10: running again grants nothing more.
        self::assertSame(0, $service->awardFor($user));

        $owned = $userBadges->findBy(['user' => $user]);
        self::assertCount(1, $owned);
        $ownedBadge = $owned[0]->getBadge();
        self::assertNotNull($ownedBadge);
        self::assertSame($low->getId(), $ownedBadge->getId());

        self::assertNotEmpty($notifications->findBy(['recipient' => $user]));
    }
}
