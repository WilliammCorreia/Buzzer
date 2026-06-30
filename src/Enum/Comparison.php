<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Direction of a "player prop" prediction relative to the threshold.
 */
enum Comparison: string
{
    case Over = 'OVER';
    case Under = 'UNDER';

    public function label(): string
    {
        return match ($this) {
            self::Over => 'Au-dessus',
            self::Under => 'En dessous',
        };
    }
}
