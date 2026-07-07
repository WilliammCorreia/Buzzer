<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\Season;
use App\Entity\Team;
use App\Enum\GameStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests of the public JSON API (v1): payload shape and, above
 * all, that the normalization groups expose exactly the intended fields.
 */
final class ApiGamesTest extends WebTestCase
{
    public function testGamesCollectionReturnsPaginatedJson(): void
    {
        $client = static::createClient();
        $game = $this->createGame();

        $client->request('GET', '/api/v1/games');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);

        self::assertSame(1, $payload['meta']['page']);
        self::assertGreaterThanOrEqual(1, $payload['meta']['total']);
        self::assertNotEmpty($payload['data']);

        $first = $payload['data'][0];
        self::assertArrayHasKey('id', $first);
        self::assertArrayHasKey('startsAt', $first);
        self::assertArrayHasKey('status', $first);
        self::assertArrayHasKey('openForPredictions', $first);
        self::assertSame($game->getHomeTeam()?->getName(), $first['homeTeam']['name']);

        // Fields without a normalization group must never leak.
        self::assertArrayNotHasKey('apiId', $first);
        self::assertArrayNotHasKey('predictions', $first);
        // The season belongs to the `game:detail` group only.
        self::assertArrayNotHasKey('season', $first);
    }

    public function testGameDetailExposesTheSeasonThroughItsGroup(): void
    {
        $client = static::createClient();
        $game = $this->createGame();

        $client->request('GET', '/api/v1/games/'.$game->getId());

        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);

        self::assertSame($game->getId(), $payload['id']);
        self::assertSame('Saison test', $payload['season']['label']);
        self::assertArrayNotHasKey('apiId', $payload);
        self::assertArrayNotHasKey('apiId', $payload['homeTeam']);
    }

    private function createGame(): Game
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        // Fresh, collision-free data for this run.
        $suffix = uniqid();
        $home = (new Team())->setApiId(random_int(1, 2_000_000_000))->setName('Home '.$suffix)->setCode('HOM')->setCity('Home');
        $away = (new Team())->setApiId(random_int(1, 2_000_000_000))->setName('Away '.$suffix)->setCode('AWY')->setCity('Away');
        $season = (new Season())
            ->setYear(2025)
            ->setLabel('Saison test')
            ->setStartDate(new \DateTimeImmutable('-3 months'))
            ->setEndDate(new \DateTimeImmutable('+3 months'));
        $game = (new Game())
            ->setApiId(random_int(1, 2_000_000_000))
            ->setHomeTeam($home)
            ->setAwayTeam($away)
            ->setSeason($season)
            // Far in the future so the game is always first in the calendar
            // (ordered by startsAt DESC), whatever else is in the database.
            ->setStartsAt(new \DateTimeImmutable('+10 years'))
            ->setStatus(GameStatus::Scheduled);

        foreach ([$home, $away, $season, $game] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        return $game;
    }
}
