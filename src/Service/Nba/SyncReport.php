<?php

declare(strict_types=1);

namespace App\Service\Nba;

/**
 * Outcome of a synchronization pass for one resource type.
 */
final class SyncReport
{
    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;
    public int $settled = 0;

    public function total(): int
    {
        return $this->created + $this->updated + $this->skipped;
    }
}
