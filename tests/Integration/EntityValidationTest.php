<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Game;
use App\Entity\MatchWinnerPrediction;
use App\Entity\ScorePrediction;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Guards the validation constraints carried by the entities: each case asserts
 * that an incoherent object is rejected before it ever reaches the database.
 */
final class EntityValidationTest extends KernelTestCase
{
    private function validator(): ValidatorInterface
    {
        self::bootKernel();
        $validator = static::getContainer()->get(ValidatorInterface::class);
        self::assertInstanceOf(ValidatorInterface::class, $validator);

        return $validator;
    }

    /** @return list<string> */
    private function messagesFor(object $entity): array
    {
        $messages = [];
        foreach ($this->validator()->validate($entity) as $violation) {
            $messages[] = (string) $violation->getMessage();
        }

        return $messages;
    }

    private function team(string $name, string $code, int $apiId): Team
    {
        return (new Team())
            ->setApiId($apiId)
            ->setName($name)
            ->setCode($code)
            ->setCity($name);
    }

    private function game(Team $home, Team $away): Game
    {
        return (new Game())
            ->setApiId(1)
            ->setHomeTeam($home)
            ->setAwayTeam($away)
            ->setStartsAt(new \DateTimeImmutable('2026-01-01 20:00:00'));
    }

    public function testUserRejectsInvalidEmail(): void
    {
        $user = (new User())->setUsername('willix');
        $user->setEmail('pas-une-adresse');

        self::assertNotEmpty($this->messagesFor($user));
    }

    public function testUserRejectsUnknownRole(): void
    {
        $user = (new User())->setUsername('willix');
        $user->setEmail('willix@buzzer.test');
        $user->setRoles(['ROLE_SUPERHERO']);

        self::assertNotEmpty($this->messagesFor($user));
    }

    public function testGameRejectsTeamPlayingItself(): void
    {
        $lakers = $this->team('Lakers', 'LAL', 1);
        $game = $this->game($lakers, $lakers);

        self::assertContains(
            "L'équipe à l'extérieur doit être différente de l'équipe à domicile.",
            $this->messagesFor($game),
        );
    }

    public function testSeasonRejectsEndDateBeforeStartDate(): void
    {
        $season = (new Season())
            ->setYear(2025)
            ->setLabel('2025-2026')
            ->setStartDate(new \DateTimeImmutable('2026-04-01'))
            ->setEndDate(new \DateTimeImmutable('2025-10-01'));

        self::assertContains(
            'La date de fin doit être postérieure à la date de début.',
            $this->messagesFor($season),
        );
    }

    public function testScorePredictionRejectsADraw(): void
    {
        $game = $this->game($this->team('Lakers', 'LAL', 1), $this->team('Celtics', 'BOS', 2));

        $prediction = new ScorePrediction();
        $prediction->setUser(new User())->setGame($game);
        $prediction->setPredictedHomeScore(110)->setPredictedAwayScore(110);

        self::assertContains(
            'Un match NBA ne peut pas se terminer sur une égalité : les deux scores doivent être différents.',
            $this->messagesFor($prediction),
        );
    }

    public function testScorePredictionRejectsOutOfRangeScore(): void
    {
        $game = $this->game($this->team('Lakers', 'LAL', 1), $this->team('Celtics', 'BOS', 2));

        $prediction = new ScorePrediction();
        $prediction->setUser(new User())->setGame($game);
        $prediction->setPredictedHomeScore(-5)->setPredictedAwayScore(110);

        self::assertNotEmpty($this->messagesFor($prediction));
    }

    public function testMatchWinnerPredictionRejectsTeamOutsideTheGame(): void
    {
        $game = $this->game($this->team('Lakers', 'LAL', 1), $this->team('Celtics', 'BOS', 2));
        $intruder = $this->team('Bulls', 'CHI', 3);

        $prediction = new MatchWinnerPrediction();
        $prediction->setUser(new User())->setGame($game);
        $prediction->setPredictedWinner($intruder);

        self::assertContains(
            "L'équipe choisie ne participe pas à ce match.",
            $this->messagesFor($prediction),
        );
    }

    public function testMatchWinnerPredictionAcceptsATeamOfTheGame(): void
    {
        $lakers = $this->team('Lakers', 'LAL', 1);
        $game = $this->game($lakers, $this->team('Celtics', 'BOS', 2));

        $user = (new User())->setUsername('willix');
        $user->setEmail('willix@buzzer.test');

        $prediction = new MatchWinnerPrediction();
        $prediction->setUser($user)->setGame($game);
        $prediction->setPredictedWinner($lakers);

        self::assertSame([], $this->messagesFor($prediction));
    }
}
