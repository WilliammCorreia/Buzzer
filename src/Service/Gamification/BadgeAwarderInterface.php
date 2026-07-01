<?php

declare(strict_types=1);

namespace App\Service\Gamification;

use App\Entity\User;

interface BadgeAwarderInterface
{
    /**
     * Awards every badge whose threshold the user has reached and does not own
     * yet, and returns the number of newly granted badges.
     */
    public function awardFor(User $user): int;
}
