<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Game;
use App\Entity\MatchWinnerPrediction;
use App\Entity\Player;
use App\Entity\PlayerPropPrediction;
use App\Entity\Prediction;
use App\Entity\ScorePrediction;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\Comparison;
use App\Enum\StatType;
use App\Form\PredictionType;
use App\Repository\PredictionRepository;
use App\Security\Voter\PredictionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PredictionController extends AbstractController
{
    private const VALID_TYPES = ['match_winner', 'score', 'player_prop'];

    #[Route('/matchs/{id}/pronostiquer', name: 'app_prediction_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Game $game, Request $request, EntityManagerInterface $entityManager, PredictionRepository $predictions): Response
    {
        // RG-01: predictions are only accepted while the game is open.
        if (!$game->isOpenForPredictions()) {
            $this->addFlash('error', 'Les pronostics sont clôturés pour ce match.');

            return $this->redirectToRoute('app_game_show', ['id' => $game->getId()]);
        }

        $type = $request->query->getString('type', 'match_winner');
        if (!\in_array($type, self::VALID_TYPES, true)) {
            $type = 'match_winner';
        }

        $form = $this->createForm(PredictionType::class, null, [
            'game' => $game,
            'prediction_type' => $type,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            \assert($user instanceof User);
            $chosenType = (string) $form->get('type')->getData();

            // RG-02: one prediction per game and per type.
            if ($predictions->findOneOfTypeForUserAndGame($user, $game, $chosenType) !== null) {
                $this->addFlash('error', 'Vous avez déjà un pronostic de ce type pour ce match.');

                return $this->redirectToRoute('app_game_show', ['id' => $game->getId()]);
            }

            $prediction = $this->buildPrediction($chosenType, $form);
            $prediction->setUser($user)->setGame($game);

            $entityManager->persist($prediction);
            $entityManager->flush();

            $this->addFlash('success', 'Pronostic enregistré ! Bonne chance.');

            return $this->redirectToRoute('app_game_show', ['id' => $game->getId()]);
        }

        return $this->render('prediction/new.html.twig', [
            'game' => $game,
            'form' => $form,
        ]);
    }

    #[Route('/pronostics/{id}/supprimer', name: 'app_prediction_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PredictionVoter::DELETE, subject: 'prediction')]
    public function delete(Prediction $prediction, Request $request, EntityManagerInterface $entityManager): Response
    {
        $gameId = $prediction->getGame()?->getId();

        if ($this->isCsrfTokenValid('delete_prediction_'.$prediction->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($prediction);
            $entityManager->flush();
            $this->addFlash('success', 'Pronostic supprimé.');
        }

        return $this->redirectToRoute('app_game_show', ['id' => $gameId]);
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function buildPrediction(string $type, FormInterface $form): Prediction
    {
        return match ($type) {
            'score' => (new ScorePrediction())
                ->setPredictedHomeScore((int) $form->get('predictedHomeScore')->getData())
                ->setPredictedAwayScore((int) $form->get('predictedAwayScore')->getData()),
            'player_prop' => $this->buildPlayerProp($form),
            default => $this->buildMatchWinner($form),
        };
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function buildMatchWinner(FormInterface $form): MatchWinnerPrediction
    {
        $winner = $form->get('predictedWinner')->getData();
        \assert($winner instanceof Team);

        return (new MatchWinnerPrediction())->setPredictedWinner($winner);
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function buildPlayerProp(FormInterface $form): PlayerPropPrediction
    {
        $player = $form->get('player')->getData();
        $statType = $form->get('statType')->getData();
        $comparison = $form->get('comparison')->getData();
        \assert($player instanceof Player);
        \assert($statType instanceof StatType);
        \assert($comparison instanceof Comparison);

        return (new PlayerPropPrediction())
            ->setPlayer($player)
            ->setStatType($statType)
            ->setComparison($comparison)
            ->setPredictedValue((float) $form->get('predictedValue')->getData());
    }
}
