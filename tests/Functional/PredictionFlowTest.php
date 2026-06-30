<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\GameStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PredictionFlowTest extends WebTestCase
{
    public function testAuthenticatedUserSeesDynamicPredictionForm(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        // Fresh, collision-free data for this run.
        $suffix = uniqid();
        $home = (new Team())->setApiId(random_int(1, 2_000_000_000))->setName('Home '.$suffix)->setCode('HOM')->setCity('Home');
        $away = (new Team())->setApiId(random_int(1, 2_000_000_000))->setName('Away '.$suffix)->setCode('AWY')->setCity('Away');
        $game = (new Game())
            ->setApiId(random_int(1, 2_000_000_000))
            ->setHomeTeam($home)
            ->setAwayTeam($away)
            ->setStartsAt(new \DateTimeImmutable('+5 days'))
            ->setStatus(GameStatus::Scheduled);
        $user = (new User())
            ->setEmail('pred_'.$suffix.'@buzzer.test')
            ->setUsername('u'.substr($suffix, -6))
            ->setPassword('not-used-here');

        foreach ([$home, $away, $game, $user] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/matchs/'.$game->getId().'/pronostiquer');

        self::assertResponseIsSuccessful();
        // The type selector is always present...
        self::assertSelectorExists('select[name="prediction[type]"]');
        // ...and the default type (match winner) renders the team choice dynamically.
        self::assertSelectorExists('[name="prediction[predictedWinner]"]');
    }
}
