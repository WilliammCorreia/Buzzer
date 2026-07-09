<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\League;
use App\Entity\LeagueMembership;
use App\Entity\User;
use App\Enum\LeagueRole;
use App\Form\JoinLeagueType;
use App\Form\LeagueInviteType;
use App\Form\LeagueType;
use App\Repository\LeagueMembershipRepository;
use App\Repository\LeagueRepository;
use App\Security\Voter\LeagueVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/leagues')]
class LeagueController extends AbstractController
{
    #[Route('', name: 'app_league_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(LeagueMembershipRepository $membershipRepository): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $this->render('league/index.html.twig', [
            'memberships' => $membershipRepository->findForUserWithLeague($user),
        ]);
    }

    #[Route('/new', name: 'app_league_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $entityManager, LeagueRepository $leagueRepository): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        // Pré-remplissage de owner + inviteCode AVANT de créer le formulaire : ces champs
        // sont NotBlank/non-nullable sur l'entité mais absents du LeagueType mappé.
        $league = (new League())
            ->setOwner($user)
            ->setInviteCode($this->generateUniqueInviteCode($leagueRepository));

        $form = $this->createForm(LeagueType::class, $league);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $membership = (new LeagueMembership())
                ->setUser($user)
                ->setRole(LeagueRole::Owner);
            $league->addMembership($membership);

            $entityManager->persist($league);
            $entityManager->persist($membership);
            $entityManager->flush();

            $this->addFlash('success', 'Ligue créée !');

            return $this->redirectToRoute('app_league_show', ['id' => $league->getId()]);
        }

        return $this->render('league/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_league_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(League $league, LeagueMembershipRepository $membershipRepository): Response
    {
        return $this->render('league/show.html.twig', [
            'league' => $league,
            'leaderboard' => $membershipRepository->findLeaderboard($league),
            'inviteForm' => $this->createForm(LeagueInviteType::class),
        ]);
    }

    #[Route('/join', name: 'app_league_join', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function join(
        Request $request,
        LeagueRepository $leagueRepository,
        LeagueMembershipRepository $membershipRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        \assert($user instanceof User);

        $form = $this->createForm(JoinLeagueType::class, [
            'inviteCode' => strtoupper((string) $request->query->get('code', '')),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $code = strtoupper(trim((string) $form->get('inviteCode')->getData()));
            $league = $leagueRepository->findOneByInviteCode($code);

            if ($league === null) {
                $this->addFlash('error', "Code d'invitation invalide.");

                return $this->redirectToRoute('app_league_join');
            }

            if ($membershipRepository->findOneBy(['user' => $user, 'league' => $league]) !== null) {
                $this->addFlash('error', 'Vous êtes déjà membre de cette ligue.');

                return $this->redirectToRoute('app_league_show', ['id' => $league->getId()]);
            }

            $membership = (new LeagueMembership())
                ->setUser($user)
                ->setRole(LeagueRole::Member);
            $league->addMembership($membership);

            $entityManager->persist($membership);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Vous avez rejoint la ligue « %s ».', $league->getName()));

            return $this->redirectToRoute('app_league_show', ['id' => $league->getId()]);
        }

        return $this->render('league/join.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/invite', name: 'app_league_invite', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(LeagueVoter::MANAGE, subject: 'league')]
    public function invite(
        League $league,
        Request $request,
        MailerInterface $mailer,
        #[Autowire(param: 'app.mailer_from')] string $mailerFrom,
    ): Response {
        $form = $this->createForm(LeagueInviteType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (string) $form->get('email')->getData();

            $mailer->send((new TemplatedEmail())
                ->from($mailerFrom)
                ->to($email)
                ->subject(sprintf('Invitation à rejoindre la ligue « %s »', $league->getName()))
                ->htmlTemplate('league/invite_email.html.twig')
                ->context(['league' => $league, 'inviter' => $this->getUser()]));

            $this->addFlash('success', sprintf('Invitation envoyée à %s.', $email));
        } else {
            $this->addFlash('error', 'Adresse e-mail invalide.');
        }

        return $this->redirectToRoute('app_league_show', ['id' => $league->getId()]);
    }

    private function generateUniqueInviteCode(LeagueRepository $leagueRepository): string
    {
        do {
            $code = strtoupper(bin2hex(random_bytes(6)));
        } while ($leagueRepository->findOneByInviteCode($code) !== null);

        return $code;
    }
}
