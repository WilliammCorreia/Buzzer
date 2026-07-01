<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Repository\SeasonRepository;
use App\Repository\TeamRepository;
use App\Service\Nba\NbaApiClient;
use App\Service\Nba\NbaSynchronizer;
use App\Service\Scoring\PredictionSettlementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class NbaSynchronizerTest extends KernelTestCase
{
    public function testSyncTeamsUpsertsFranchisesAndSkipsSpecialTeams(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $em = $container->get(EntityManagerInterface::class);
        $teams = $container->get(TeamRepository::class);
        $playerRepository = $container->get(PlayerRepository::class);
        $gameRepository = $container->get(GameRepository::class);
        $seasonRepository = $container->get(SeasonRepository::class);
        $settlement = $container->get(PredictionSettlementService::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(TeamRepository::class, $teams);
        self::assertInstanceOf(PlayerRepository::class, $playerRepository);
        self::assertInstanceOf(GameRepository::class, $gameRepository);
        self::assertInstanceOf(SeasonRepository::class, $seasonRepository);
        self::assertInstanceOf(PredictionSettlementService::class, $settlement);

        $apiId = random_int(1_000_000, 2_000_000_000);
        $payload = [
            'errors' => [],
            'response' => [
                [
                    'id' => $apiId,
                    'name' => 'Test Franchise',
                    'code' => 'TSF',
                    'city' => 'Test City',
                    'logo' => 'https://example.test/logo.png',
                    'nbaFranchise' => true,
                    'leagues' => ['standard' => ['conference' => 'West', 'division' => 'Pacific']],
                ],
                [
                    'id' => $apiId + 1,
                    'name' => 'Team World',
                    'nbaFranchise' => false, // All-Star team -> must be skipped
                ],
            ],
        ];

        $api = new NbaApiClient(new MockHttpClient(new MockResponse((string) json_encode($payload))));
        $synchronizer = new NbaSynchronizer($api, $em, $teams, $playerRepository, $gameRepository, $seasonRepository, $settlement);

        $report = $synchronizer->syncTeams();

        self::assertSame(1, $report->created);
        self::assertSame(1, $report->skipped);

        $team = $teams->findOneBy(['apiId' => $apiId]);
        self::assertNotNull($team);
        self::assertSame('Test Franchise', $team->getName());
        self::assertSame('West', $team->getConference());
        self::assertSame('Pacific', $team->getDivision());
    }
}
