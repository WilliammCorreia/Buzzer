<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Game;
use App\Entity\MatchWinnerPrediction;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\GameStatus;
use App\Enum\PredictionStatus;
use App\Message\SettleGameMessage;
use App\MessageHandler\SettleGameMessageHandler;
use App\Service\Scoring\ScoringPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SettleGameMessageHandlerTest extends KernelTestCase
{
    public function testHandlerSettlesFinishedGame(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $em = $container->get(EntityManagerInterface::class);
        $handler = $container->get(SettleGameMessageHandler::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(SettleGameMessageHandler::class, $handler);

        $home = (new Team())->setApiId(random_int(1, 2_000_000_000))->setName('H')->setCode('H')->setCity('H');
        $away = (new Team())->setApiId(random_int(1, 2_000_000_000))->setName('A')->setCode('A')->setCity('A');
        $game = (new Game())
            ->setApiId(random_int(1, 2_000_000_000))
            ->setHomeTeam($home)->setAwayTeam($away)
            ->setStartsAt(new \DateTimeImmutable('-1 day'))
            ->setStatus(GameStatus::Finished)->setHomeScore(110)->setAwayScore(100);
        $user = (new User())->setEmail('async_'.uniqid().'@buzzer.test')->setUsername('a'.substr(uniqid(), -6))->setPassword('x');

        $prediction = (new MatchWinnerPrediction())->setPredictedWinner($home);
        $prediction->setUser($user);
        $game->addPrediction($prediction); // maintain both sides of the relation

        foreach ([$home, $away, $game, $user, $prediction] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $gameId = $game->getId();
        self::assertNotNull($gameId);

        // Simulate the worker consuming the message.
        $handler(new SettleGameMessage($gameId));

        self::assertSame(PredictionStatus::Won, $prediction->getStatus());
        self::assertSame(ScoringPolicy::POINTS_MATCH_WINNER, $prediction->getPointsAwarded());
    }
}
