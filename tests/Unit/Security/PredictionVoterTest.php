<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Game;
use App\Entity\MatchWinnerPrediction;
use App\Entity\Prediction;
use App\Entity\User;
use App\Enum\GameStatus;
use App\Security\Voter\PredictionVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class PredictionVoterTest extends TestCase
{
    private PredictionVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new PredictionVoter();
    }

    private function user(string $email): User
    {
        return (new User())->setEmail($email)->setUsername(substr($email, 0, 5));
    }

    private function predictionOn(User $author, GameStatus $status, string $startsAt): Prediction
    {
        $game = (new Game())
            ->setApiId(1)
            ->setStatus($status)
            ->setStartsAt(new \DateTimeImmutable($startsAt));

        $prediction = new MatchWinnerPrediction();
        $prediction->setUser($author)->setGame($game);

        return $prediction;
    }

    private function vote(User $voterUser, Prediction $prediction): int
    {
        $token = new UsernamePasswordToken($voterUser, 'main', $voterUser->getRoles());

        return $this->voter->vote($token, $prediction, [PredictionVoter::EDIT]);
    }

    public function testAuthorCanEditWhileGameIsOpen(): void
    {
        $author = $this->user('author@buzzer.test');
        $prediction = $this->predictionOn($author, GameStatus::Scheduled, '+2 days');

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($author, $prediction));
    }

    public function testAuthorCannotEditAfterTipOff(): void
    {
        $author = $this->user('author@buzzer.test');
        $prediction = $this->predictionOn($author, GameStatus::Finished, '-1 day');

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($author, $prediction));
    }

    public function testNonAuthorCannotEdit(): void
    {
        $author = $this->user('author@buzzer.test');
        $other = $this->user('other@buzzer.test');
        $prediction = $this->predictionOn($author, GameStatus::Scheduled, '+2 days');

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($other, $prediction));
    }

    public function testAbstainsOnUnsupportedAttribute(): void
    {
        $author = $this->user('author@buzzer.test');
        $prediction = $this->predictionOn($author, GameStatus::Scheduled, '+2 days');
        $token = new UsernamePasswordToken($author, 'main', $author->getRoles());

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($token, $prediction, ['SOMETHING_ELSE'])
        );
    }
}
