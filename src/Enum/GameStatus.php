<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Lifecycle of a NBA game, mirrored from the upstream API feed.
 */
enum GameStatus: string
{
    case Scheduled = 'SCHEDULED';
    case Live = 'LIVE';
    case Finished = 'FINISHED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'À venir',
            self::Live => 'En cours',
            self::Finished => 'Terminé',
            self::Cancelled => 'Annulé',
        };
    }

    /**
     * Predictions can only be placed/edited while the game has not tipped off.
     */
    public function isOpenForPredictions(): bool
    {
        return $this === self::Scheduled;
    }
}
