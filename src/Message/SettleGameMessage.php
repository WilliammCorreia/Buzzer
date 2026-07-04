<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Asks for the (heavy) settlement of a finished game to be processed
 * asynchronously by a worker.
 */
final class SettleGameMessage
{
    public function __construct(
        public readonly int $gameId,
    ) {
    }
}
