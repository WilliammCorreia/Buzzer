<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Game;
use App\Entity\User;
use App\Form\CommentType;
use App\Security\Voter\CommentVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CommentController extends AbstractController
{
    // No #[IsGranted] here: this action is embedded anonymously via
    // render(controller(...)) from game/show.html.twig, so it must stay
    // reachable for visitors — the template itself shows a "log in" prompt.
    #[Route('/matchs/{id}/commentaires/formulaire', name: 'app_comment_new_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function newForm(Game $game): Response
    {
        return $this->render('comment/_form.html.twig', [
            'game' => $game,
            'form' => $this->createForm(CommentType::class),
        ]);
    }

    #[Route('/matchs/{id}/commentaires', name: 'app_comment_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Game $game, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        // Pré-construction author + game avant createForm() (mêmes contraintes
        // NotBlank/non-nullable que pour LeagueType, cf. le plan).
        $comment = (new Comment())->setAuthor($user)->setGame($game);
        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($comment);
            $entityManager->flush();
            $this->addFlash('success', 'Commentaire publié.');
        } else {
            $this->addFlash('error', "Votre commentaire n'a pas pu être publié.");
        }

        return $this->redirectToRoute('app_game_show', ['id' => $game->getId()]);
    }

    #[Route('/commentaires/{id}/supprimer', name: 'app_comment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(CommentVoter::DELETE, subject: 'comment')]
    public function delete(Comment $comment, Request $request, EntityManagerInterface $entityManager): Response
    {
        $gameId = $comment->getGame()?->getId();

        if ($this->isCsrfTokenValid('delete_comment_'.$comment->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($comment);
            $entityManager->flush();
            $this->addFlash('success', 'Commentaire supprimé.');
        }

        return $this->redirectToRoute('app_game_show', ['id' => $gameId]);
    }
}
