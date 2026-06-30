<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Category of an in-app notification.
 */
enum NotificationType: string
{
    case PredictionLock = 'PREDICTION_LOCK';
    case Result = 'RESULT';
    case Badge = 'BADGE';
    case LeagueInvite = 'LEAGUE_INVITE';

    public function label(): string
    {
        return match ($this) {
            self::PredictionLock => 'Verrouillage imminent',
            self::Result => 'Résultat de pronostic',
            self::Badge => 'Badge obtenu',
            self::LeagueInvite => 'Invitation à une ligue',
        };
    }
}
