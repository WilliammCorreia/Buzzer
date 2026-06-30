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
     * Maps a game or prediction status to a Bootstrap contextual class,
     * e.g. `{{ game.status|status_class }}` -> "secondary".
     */
    public function statusClass(GameStatus|PredictionStatus $status): string
    {
        return match ($status) {
            GameStatus::Scheduled => 'secondary',
            GameStatus::Live, PredictionStatus::Won => 'success',
            GameStatus::Finished => 'dark',
            GameStatus::Cancelled, PredictionStatus::Lost => 'danger',
            PredictionStatus::Pending => 'warning',
        };
    }
}
