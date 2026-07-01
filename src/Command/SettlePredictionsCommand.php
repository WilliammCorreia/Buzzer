<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\GameRepository;
use App\Service\Scoring\PredictionSettlementService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:predictions:settle',
    description: 'Règle les pronostics des matchs terminés (points, classements, badges).',
)]
final class SettlePredictionsCommand extends Command
{
    public function __construct(
        private readonly GameRepository $games,
        private readonly PredictionSettlementService $settlement,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $games = $this->games->findFinishedWithPendingPredictions();
        if ($games === []) {
            $io->info('Aucun match terminé en attente de règlement.');

            return Command::SUCCESS;
        }

        $settled = 0;
        $badges = 0;
        foreach ($games as $game) {
            $report = $this->settlement->settleGame($game);
            $settled += $report->settled();
            $badges += $report->badgesAwarded;
        }

        $io->success(sprintf(
            '%d match(s) réglé(s), %d pronostic(s) évalué(s), %d badge(s) attribué(s).',
            \count($games),
            $settled,
            $badges,
        ));

        return Command::SUCCESS;
    }
}
