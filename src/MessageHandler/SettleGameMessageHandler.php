<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SettleGameMessage;
use App\Repository\GameRepository;
use App\Service\Scoring\PredictionSettlementService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Worker-side settlement of a finished game (UC-52, processed asynchronously).
 */
#[AsMessageHandler]
final class SettleGameMessageHandler
{
    public function __construct(
        private readonly GameRepository $games,
        private readonly PredictionSettlementService $settlement,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SettleGameMessage $message): void
    {
        $game = $this->games->find($message->gameId);
        if ($game === null || !$game->isFinished() || $game->getHomeScore() === null || $game->getAwayScore() === null) {
            // Game removed or not settleable (anymore): nothing to do.
            return;
        }

        $report = $this->settlement->settleGame($game);

        $this->logger->info('Game {id} settled: {won} won, {lost} lost, {badges} badge(s) awarded.', [
            'id' => $message->gameId,
            'won' => $report->won,
            'lost' => $report->lost,
            'badges' => $report->badgesAwarded,
        ]);
    }
}
