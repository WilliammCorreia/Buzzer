<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\SettleGameMessage;
use App\Repository\GameRepository;
use App\Service\Scoring\PredictionSettlementService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:predictions:settle',
    description: 'Règle les pronostics des matchs terminés (points, classements, badges).',
)]
final class SettlePredictionsCommand extends Command
{
    public function __construct(
        private readonly GameRepository $games,
        private readonly PredictionSettlementService $settlement,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'async',
            null,
            InputOption::VALUE_NONE,
            'Dispatch le règlement dans la file (Messenger) au lieu de le traiter immédiatement.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $async = (bool) $input->getOption('async');

        $games = $this->games->findFinishedWithPendingPredictions();
        if ($games === []) {
            $io->info('Aucun match terminé en attente de règlement.');

            return Command::SUCCESS;
        }

        if ($async) {
            foreach ($games as $game) {
                $this->bus->dispatch(new SettleGameMessage((int) $game->getId()));
            }
            $io->success(sprintf('%d match(s) mis en file pour règlement asynchrone.', \count($games)));

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
