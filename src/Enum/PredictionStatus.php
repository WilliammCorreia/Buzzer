<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Lifecycle of a prediction: PENDING until the game is settled, then WON or LOST.
 */
enum PredictionStatus: string
{
    case Pending = 'PENDING';
    case Won = 'WON';
    case Lost = 'LOST';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Won => 'Gagné',
            self::Lost => 'Perdu',
        };
    }
}
