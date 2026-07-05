<?php

declare(strict_types=1);

namespace App\Twig;

use App\Enum\GameStatus;
use App\Enum\PredictionStatus;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Application Twig extension: custom filters used across the templates.
 */
final class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('status_class', $this->statusClass(...)),
        ];
    }

    /**
     * Maps a game or prediction status to Tailwind badge color classes,
     * e.g. `<span class="badge {{ game.status|status_class }}">`.
     */
    public function statusClass(GameStatus|PredictionStatus $status): string
    {
        return match ($status) {
            GameStatus::Scheduled => 'bg-slate-100 text-slate-700 ring-slate-200',
            GameStatus::Live, PredictionStatus::Won => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
            GameStatus::Finished => 'bg-slate-800 text-white ring-slate-700',
            GameStatus::Cancelled, PredictionStatus::Lost => 'bg-red-100 text-red-700 ring-red-200',
            PredictionStatus::Pending => 'bg-amber-100 text-amber-800 ring-amber-200',
        };
    }
}
