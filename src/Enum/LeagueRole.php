<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Role of a user inside a league membership.
 */
enum LeagueRole: string
{
    case Owner = 'OWNER';
    case Member = 'MEMBER';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Propriétaire',
            self::Member => 'Membre',
        };
    }
}
