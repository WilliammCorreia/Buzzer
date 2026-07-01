<?php

declare(strict_types=1);

namespace App\Service\Nba;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin wrapper around the scoped NBA HttpClient (API-Sports).
 *
 * Each endpoint returns the decoded `response` array of the API envelope
 * ({ get, parameters, errors, results, response }). API-level errors are turned
 * into exceptions so the caller only deals with the payload.
 */
final class NbaApiClient
{
    public function __construct(
        #[Autowire(service: 'nba.client')]
        private readonly HttpClientInterface $client,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTeams(): array
    {
        return $this->get('/teams');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPlayers(int $teamApiId, int $season): array
    {
        return $this->get('/players', ['team' => $teamApiId, 'season' => $season]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getGamesBySeason(int $season): array
    {
        return $this->get('/games', ['season' => $season]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getGamesByDate(string $date): array
    {
        return $this->get('/games', ['date' => $date]);
    }

    /**
     * @param array<string, int|string> $query
     *
     * @return array<int, array<string, mixed>>
     */
    private function get(string $path, array $query = []): array
    {
        $data = $this->client->request('GET', $path, ['query' => $query])->toArray();

        // API-Sports reports auth/quota problems in the "errors" field with a 200.
        $errors = $data['errors'] ?? [];
        if (\is_array($errors) && $errors !== []) {
            throw new \RuntimeException('Erreur API NBA : '.json_encode($errors, \JSON_UNESCAPED_UNICODE));
        }

        $response = $data['response'] ?? [];
        if (!\is_array($response)) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $response */
        return $response;
    }
}
