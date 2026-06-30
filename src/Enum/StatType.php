<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Player statistic targeted by a "player prop" prediction.
 */
enum StatType: string
{
    case Points = 'POINTS';
    case Rebounds = 'REBOUNDS';
    case Assists = 'ASSISTS';

    public function label(): string
    {
        return match ($this) {
            self::Points => 'Points',
            self::Rebounds => 'Rebonds',
            self::Assists => 'Passes décisives',
        };
    }
}
