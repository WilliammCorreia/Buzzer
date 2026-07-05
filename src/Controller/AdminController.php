<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MatchWinnerPrediction;
use App\Entity\PlayerPropPrediction;
use App\Entity\ScorePrediction;
use App\Enum\GameStatus;
use App\Enum\PredictionStatus;
use App\Repository\BadgeRepository;
use App\Repository\CommentRepository;
use App\Repository\GameRepository;
use App\Repository\LeagueRepository;
use App\Repository\PlayerRepository;
use App\Repository\PredictionRepository;
use App\Repository\TeamRepository;
use App\Repository\UserBadgeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(
        UserRepository $users,
        TeamRepository $teams,
        PlayerRepository $players,
        GameRepository $games,
        PredictionRepository $predictions,
        LeagueRepository $leagues,
        CommentRepository $comments,
        BadgeRepository $badges,
        UserBadgeRepository $userBadges,
        EntityManagerInterface $entityManager,
    ): Response {
        $totalPredictions = $predictions->count([]);

        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'users' => $users->count([]),
                'teams' => $teams->count([]),
                'players' => $players->count([]),
                'games' => $games->count([]),
                'predictions' => $totalPredictions,
                'leagues' => $leagues->count([]),
                'comments' => $comments->count([]),
                'badges' => $badges->count([]),
                'badgesAwarded' => $userBadges->count([]),
            ],
            'gamesByStatus' => [
                ['label' => 'À venir', 'count' => $games->count(['status' => GameStatus::Scheduled]), 'class' => 'bg-slate-400'],
                ['label' => 'En direct', 'count' => $games->count(['status' => GameStatus::Live]), 'class' => 'bg-emerald-500'],
                ['label' => 'Terminés', 'count' => $games->count(['status' => GameStatus::Finished]), 'class' => 'bg-slate-800'],
                ['label' => 'Annulés', 'count' => $games->count(['status' => GameStatus::Cancelled]), 'class' => 'bg-red-500'],
            ],
            'predictionsByStatus' => [
                ['label' => 'En attente', 'count' => $predictions->count(['status' => PredictionStatus::Pending]), 'class' => 'bg-amber-400'],
                ['label' => 'Gagnés', 'count' => $predictions->count(['status' => PredictionStatus::Won]), 'class' => 'bg-emerald-500'],
                ['label' => 'Perdus', 'count' => $predictions->count(['status' => PredictionStatus::Lost]), 'class' => 'bg-red-500'],
            ],
            'predictionsByType' => [
                ['label' => 'Vainqueur', 'count' => $entityManager->getRepository(MatchWinnerPrediction::class)->count([]), 'class' => 'bg-orange-500'],
                ['label' => 'Score exact', 'count' => $entityManager->getRepository(ScorePrediction::class)->count([]), 'class' => 'bg-sky-500'],
                ['label' => 'Perf. joueur', 'count' => $entityManager->getRepository(PlayerPropPrediction::class)->count([]), 'class' => 'bg-violet-500'],
            ],
            'totalPredictions' => $totalPredictions,
            'topScorers' => $predictions->topScorers(6),
        ]);
    }

    #[Route('/utilisateurs', name: 'app_admin_users', methods: ['GET'])]
    public function users(UserRepository $users): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $users->findBy([], ['createdAt' => 'DESC']),
        ]);
    }
}
