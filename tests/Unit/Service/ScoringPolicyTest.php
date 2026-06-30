<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Game;
use App\Entity\MatchWinnerPrediction;
use App\Entity\PlayerPropPrediction;
use App\Entity\ScorePrediction;
use App\Entity\Team;
use App\Enum\Comparison;
use App\Enum\GameStatus;
use App\Enum\StatType;
use App\Service\Scoring\ScoringPolicy;
use PHPUnit\Framework\TestCase;

final class ScoringPolicyTest extends TestCase
{
    private ScoringPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new ScoringPolicy();
    }

    /**
     * @return array{0: Game, 1: Team, 2: Team} [game, homeTeam, awayTeam]
     */
    private function finishedGame(int $homeScore, int $awayScore): array
    {
        $home = (new Team())->setApiId(1)->setName('Home')->setCode('HOM')->setCity('Home');
        $away = (new Team())->setApiId(2)->setName('Away')->setCode('AWY')->setCity('Away');
        $game = (new Game())
            ->setApiId(10)
            ->setHomeTeam($home)
            ->setAwayTeam($away)
            ->setStartsAt(new \DateTimeImmutable('-1 day'))
            ->setStatus(GameStatus::Finished)
            ->setHomeScore($homeScore)
            ->setAwayScore($awayScore);

        return [$game, $home, $away];
    }

    public function testMatchWinner(): void
    {
        [$game, $home, $away] = $this->finishedGame(110, 100);

        $right = new MatchWinnerPrediction();
        $right->setGame($game);
        $right->setPredictedWinner($home);
        self::assertSame(ScoringPolicy::POINTS_MATCH_WINNER, $this->policy->pointsForMatchWinner($right));

        $wrong = new MatchWinnerPrediction();
        $wrong->setGame($game);
        $wrong->setPredictedWinner($away);
        self::assertSame(0, $this->policy->pointsForMatchWinner($wrong));
    }

    public function testScoreExactBeatsApproximationBeatsMiss(): void
    {
        [$game] = $this->finishedGame(110, 104);

        $exact = (new ScorePrediction())->setPredictedHomeScore(110)->setPredictedAwayScore(104);
        $exact->setGame($game);
        self::assertSame(ScoringPolicy::POINTS_SCORE_EXACT, $this->policy->pointsForScore($exact));

        $approx = (new ScorePrediction())->setPredictedHomeScore(112)->setPredictedAwayScore(101);
        $approx->setGame($game);
        self::assertSame(ScoringPolicy::POINTS_SCORE_RIGHT_WINNER, $this->policy->pointsForScore($approx));

        $miss = (new ScorePrediction())->setPredictedHomeScore(99)->setPredictedAwayScore(105);
        $miss->setGame($game);
        self::assertSame(0, $this->policy->pointsForScore($miss));
    }

    public function testPlayerProp(): void
    {
        $over = (new PlayerPropPrediction())
            ->setStatType(StatType::Points)
            ->setPredictedValue(25.5)
            ->setComparison(Comparison::Over);

        self::assertSame(ScoringPolicy::POINTS_PLAYER_PROP, $this->policy->pointsForPlayerProp($over, 31.0));
        self::assertSame(0, $this->policy->pointsForPlayerProp($over, 20.0));
    }
}
