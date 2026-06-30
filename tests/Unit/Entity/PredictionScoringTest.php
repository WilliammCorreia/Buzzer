<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Game;
use App\Entity\MatchWinnerPrediction;
use App\Entity\PlayerPropPrediction;
use App\Entity\ScorePrediction;
use App\Entity\Team;
use App\Enum\Comparison;
use App\Enum\GameStatus;
use App\Enum\StatType;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the polymorphic prediction scoring logic (Single Table
 * Inheritance subtypes). No database is involved.
 */
final class PredictionScoringTest extends TestCase
{
    private function finishedGame(int $homeScore, int $awayScore): Game
    {
        $home = (new Team())->setApiId(1)->setName('Home')->setCode('HOM')->setCity('Home City');
        $away = (new Team())->setApiId(2)->setName('Away')->setCode('AWY')->setCity('Away City');

        return (new Game())
            ->setApiId(99)
            ->setHomeTeam($home)
            ->setAwayTeam($away)
            ->setStartsAt(new \DateTimeImmutable('-1 day'))
            ->setStatus(GameStatus::Finished)
            ->setHomeScore($homeScore)
            ->setAwayScore($awayScore);
    }

    public function testMatchWinnerPredictionIsCorrectWhenPredictedTeamWins(): void
    {
        $game = $this->finishedGame(110, 100);

        $right = new MatchWinnerPrediction();
        $right->setGame($game)->setUser(null);
        $right->setPredictedWinner($game->getHomeTeam());
        self::assertTrue($right->isCorrect());

        $wrong = new MatchWinnerPrediction();
        $wrong->setGame($game);
        $wrong->setPredictedWinner($game->getAwayTeam());
        self::assertFalse($wrong->isCorrect());
    }

    public function testScorePredictionExactMatch(): void
    {
        $game = $this->finishedGame(110, 104);

        $exact = (new ScorePrediction())->setPredictedHomeScore(110)->setPredictedAwayScore(104);
        $exact->setGame($game);
        self::assertTrue($exact->isExact());
        self::assertTrue($exact->hasCorrectWinner());

        $approx = (new ScorePrediction())->setPredictedHomeScore(108)->setPredictedAwayScore(101);
        $approx->setGame($game);
        self::assertFalse($approx->isExact());
        self::assertTrue($approx->hasCorrectWinner(), 'Right winner but wrong exact score');
    }

    public function testPlayerPropOverUnder(): void
    {
        $over = (new PlayerPropPrediction())
            ->setStatType(StatType::Points)
            ->setPredictedValue(25.5)
            ->setComparison(Comparison::Over);
        self::assertTrue($over->isCorrect(30.0));
        self::assertFalse($over->isCorrect(20.0));

        $under = (new PlayerPropPrediction())
            ->setStatType(StatType::Rebounds)
            ->setPredictedValue(10.5)
            ->setComparison(Comparison::Under);
        self::assertTrue($under->isCorrect(8.0));
        self::assertFalse($under->isCorrect(12.0));
    }
}
