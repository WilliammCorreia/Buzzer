<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\League;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Fine-grained permissions on a {@see League}.
 *
 * Cahier des charges §3.4 / RG-08: a league may only be managed (renamed, member
 * excluded, deleted) by its owner — or by an administrator.
 *
 * @extends Voter<string, League>
 */
final class LeagueVoter extends Voter
{
    public const MANAGE = 'LEAGUE_MANAGE';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MANAGE && $subject instanceof League;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        // Administrators can manage any league.
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        return $subject->getOwner() === $user;
    }
}
