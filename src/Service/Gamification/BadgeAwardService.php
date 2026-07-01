<?php

declare(strict_types=1);

namespace App\Service\Gamification;

use App\Entity\Badge;
use App\Entity\Notification;
use App\Entity\User;
use App\Entity\UserBadge;
use App\Enum\NotificationType;
use App\Repository\BadgeRepository;
use App\Repository\PredictionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Automatic badge attribution (UC-54).
 *
 * A user's progress metric is their total points earned (sum of pointsAwarded
 * over their predictions). Every badge whose threshold is reached is granted,
 * once and only once (RG-10, also enforced by the unique constraint on
 * user + badge), and each grant emits a notification (UC-53).
 */
final class BadgeAwardService implements BadgeAwarderInterface
{
    public function __construct(
        private readonly PredictionRepository $predictions,
        private readonly BadgeRepository $badges,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function awardFor(User $user): int
    {
        $points = $this->predictions->totalPointsForUser($user);

        $owned = [];
        foreach ($user->getUserBadges() as $userBadge) {
            $badge = $userBadge->getBadge();
            if ($badge !== null) {
                $owned[(int) $badge->getId()] = true;
            }
        }

        $awarded = 0;
        foreach ($this->badges->findAwardable($points) as $badge) {
            $badgeId = (int) $badge->getId();
            if (isset($owned[$badgeId])) {
                continue;
            }

            $userBadge = (new UserBadge())->setUser($user)->setBadge($badge);
            $user->getUserBadges()->add($userBadge); // keep the inverse side consistent
            $this->entityManager->persist($userBadge);
            $this->entityManager->persist($this->notify($user, $badge));

            $owned[$badgeId] = true;
            ++$awarded;
        }

        if ($awarded > 0) {
            $this->entityManager->flush();
        }

        return $awarded;
    }

    private function notify(User $user, Badge $badge): Notification
    {
        return (new Notification())
            ->setRecipient($user)
            ->setType(NotificationType::Badge)
            ->setMessage(sprintf('Nouveau badge débloqué : « %s » !', (string) $badge->getName()));
    }
}
