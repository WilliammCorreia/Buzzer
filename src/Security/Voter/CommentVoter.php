<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Comment;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Fine-grained permissions on a {@see Comment}.
 *
 * Cahier des charges §3.4 / RG-09:
 *  - EDIT / DELETE: reserved to the comment's author;
 *  - MODERATE (hide): reserved to ROLE_MANAGER / ROLE_ADMIN.
 *
 * @extends Voter<string, Comment>
 */
final class CommentVoter extends Voter
{
    public const EDIT = 'COMMENT_EDIT';
    public const DELETE = 'COMMENT_DELETE';
    public const MODERATE = 'COMMENT_MODERATE';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDIT, self::DELETE, self::MODERATE], true)
            && $subject instanceof Comment;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::MODERATE => $this->security->isGranted('ROLE_MANAGER'),
            self::EDIT, self::DELETE => $subject->getAuthor() === $user,
            default => false,
        };
    }
}
