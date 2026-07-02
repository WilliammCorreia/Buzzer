<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Comment;
use App\Repository\CommentRepository;
use App\Security\Voter\CommentVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/moderation')]
class ModerationController extends AbstractController
{
    #[Route('', name: 'app_moderation_index', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function index(CommentRepository $commentRepository): Response
    {
        return $this->render('moderation/index.html.twig', [
            'comments' => $commentRepository->findAllForModeration(),
        ]);
    }

    #[Route('/commentaires/{id}/basculer', name: 'app_moderation_comment_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(CommentVoter::MODERATE, subject: 'comment')]
    public function toggle(Comment $comment, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('moderate_comment_'.$comment->getId(), (string) $request->request->get('_token'))) {
            $comment->setIsHidden(!$comment->isHidden());
            $entityManager->flush();
            $this->addFlash('success', $comment->isHidden() ? 'Commentaire masqué.' : 'Commentaire réaffiché.');
        }

        return $this->redirectToRoute('app_moderation_index');
    }
}
