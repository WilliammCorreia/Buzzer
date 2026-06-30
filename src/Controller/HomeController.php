<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\GameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(GameRepository $gameRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'upcomingGames' => $gameRepository->findUpcomingWithTeams(10),
        ]);
    }
}
