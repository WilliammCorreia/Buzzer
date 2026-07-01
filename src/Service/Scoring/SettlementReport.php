<?php

declare(strict_types=1);

namespace App\Service\Scoring;

/**
 * Outcome of settling a single game: how many predictions were marked won, lost
 * or left untouched (already settled, or not yet settleable). Useful for the
 * sync command and the admin settlement report.
 */
final class SettlementReport
{
    public int $won = 0;
    public int $lost = 0;
    public int $skipped = 0;
    public int $badgesAwarded = 0;

    public function settled(): int
    {
        return $this->won + $this->lost;
    }

    public function total(): int
    {
        return $this->won + $this->lost + $this->skipped;
    }
}
