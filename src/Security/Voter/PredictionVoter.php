<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Prediction;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Fine-grained permissions on a {@see Prediction}.
 *
 * Cahier des charges §3.4 / RG-01: a prediction may only be edited or deleted
 * by its author **and** while the related game has not started yet.
 *
 * @extends Voter<string, Prediction>
 */
final class PredictionVoter extends Voter
{
    public const EDIT = 'PREDICTION_EDIT';
    public const DELETE = 'PREDICTION_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDIT, self::DELETE], true)
            && $subject instanceof Prediction;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $game = $subject->getGame();

        // Author only, and only while the game is still open for predictions.
        return $subject->getUser() === $user
            && $game !== null
            && $game->isOpenForPredictions();
    }
}
