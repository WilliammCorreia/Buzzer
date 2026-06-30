<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Game;
use App\Entity\User;
use App\Repository\CommentRepository;
use App\Repository\GameRepository;
use App\Repository\PredictionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/matchs')]
class GameController extends AbstractController
{
    private const PER_PAGE = 15;

    #[Route('', name: 'app_game_index', methods: ['GET'])]
    public function index(Request $request, GameRepository $gameRepository): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $paginator = $gameRepository->paginateCalendar($page, self::PER_PAGE);

        $total = \count($paginator);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));

        return $this->render('game/index.html.twig', [
            'games' => $paginator,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    #[Route('/{id}', name: 'app_game_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Game $game, CommentRepository $commentRepository, PredictionRepository $predictionRepository): Response
    {
        $user = $this->getUser();
        $userPredictions = $user instanceof User
            ? $predictionRepository->findForUserAndGame($user, $game)
            : [];

        return $this->render('game/show.html.twig', [
            'game' => $game,
            'comments' => $commentRepository->findForGameWithAuthor($game),
            'userPredictions' => $userPredictions,
        ]);
    }
}
