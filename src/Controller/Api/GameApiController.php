<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Game;
use App\Repository\GameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

/**
 * Public read-only JSON API (v1) exposing the game calendar.
 *
 * Serialization is driven by normalization groups (`#[Groups]` on the
 * entities): the collection exposes the compact `game:list` + `team:read`
 * representation, while the detail view adds `game:detail` + `season:read`.
 * Internal fields (upstream `apiId`, comments, predictions) carry no group
 * and are therefore never exposed.
 */
#[Route('/api/v1/games')]
final class GameApiController extends AbstractController
{
    private const PER_PAGE = 15;

    #[Route('', name: 'api_v1_game_index', methods: ['GET'])]
    public function index(Request $request, GameRepository $gameRepository): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $paginator = $gameRepository->paginateCalendar($page, self::PER_PAGE);

        $total = \count($paginator);

        return $this->json(
            [
                'meta' => [
                    'page' => $page,
                    'perPage' => self::PER_PAGE,
                    'total' => $total,
                    'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
                ],
                'data' => iterator_to_array($paginator, false),
            ],
            context: [
                AbstractNormalizer::GROUPS => ['game:list', 'team:read'],
                DateTimeNormalizer::FORMAT_KEY => \DateTimeInterface::ATOM,
            ],
        );
    }

    #[Route('/{id}', name: 'api_v1_game_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Game $game): JsonResponse
    {
        return $this->json(
            $game,
            context: [
                AbstractNormalizer::GROUPS => ['game:list', 'game:detail', 'team:read', 'season:read'],
                DateTimeNormalizer::FORMAT_KEY => \DateTimeInterface::ATOM,
            ],
        );
    }
}
