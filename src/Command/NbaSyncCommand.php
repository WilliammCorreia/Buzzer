<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Nba\NbaSynchronizer;
use App\Service\Nba\SyncReport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:nba:sync',
    description: 'Synchronise le référentiel NBA (équipes, joueurs, matchs) depuis l\'API.',
)]
final class NbaSyncCommand extends Command
{
    public function __construct(private readonly NbaSynchronizer $synchronizer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('season', 's', InputOption::VALUE_REQUIRED, 'Saison NBA (année de début, ex. 2024)', 2024)
            ->addOption('teams', null, InputOption::VALUE_NONE, 'Synchroniser les équipes')
            ->addOption('players', null, InputOption::VALUE_NONE, 'Synchroniser les joueurs (1 requête API par équipe)')
            ->addOption('games', null, InputOption::VALUE_NONE, 'Synchroniser les matchs (et régler ceux qui viennent de se terminer)')
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Limiter les matchs à une date (YYYY-MM-DD)')
            ->setHelp('Sans option de périmètre, synchronise les équipes puis les matchs de la saison.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $season = (int) $input->getOption('season');
        $date = $input->getOption('date');
        $date = \is_string($date) ? $date : null;

        $teams = (bool) $input->getOption('teams');
        $players = (bool) $input->getOption('players');
        $games = (bool) $input->getOption('games');
        if (!$teams && !$players && !$games) {
            $teams = $games = true; // sensible default
        }

        $io->title(sprintf('Synchronisation NBA — saison %d', $season));

        try {
            if ($teams) {
                $this->report($io, 'Équipes', $this->synchronizer->syncTeams());
            }
            if ($players) {
                $this->report($io, 'Joueurs', $this->synchronizer->syncPlayers($season));
            }
            if ($games) {
                $this->report($io, 'Matchs', $this->synchronizer->syncGames($season, $date));
            }
        } catch (\Throwable $e) {
            // A1 (UC-41): the sync fails cleanly, the previous catalog remains usable.
            $io->error('Échec de la synchronisation : '.$e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Synchronisation terminée.');

        return Command::SUCCESS;
    }

    private function report(SymfonyStyle $io, string $label, SyncReport $report): void
    {
        $io->section($label);
        $io->listing([
            sprintf('%d créé(s)', $report->created),
            sprintf('%d mis à jour', $report->updated),
            sprintf('%d ignoré(s)', $report->skipped),
            sprintf('%d pronostic(s) réglé(s)', $report->settled),
        ]);
    }
}
