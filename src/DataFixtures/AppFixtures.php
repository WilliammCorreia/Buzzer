<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Badge;
use App\Entity\Comment;
use App\Entity\Game;
use App\Entity\League;
use App\Entity\LeagueMembership;
use App\Entity\MatchWinnerPrediction;
use App\Entity\Player;
use App\Entity\PlayerPropPrediction;
use App\Entity\ScorePrediction;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\Comparison;
use App\Enum\GameStatus;
use App\Enum\LeagueRole;
use App\Enum\StatType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    /**
     * Shared password for every test account (see README.md).
     */
    public const TEST_PASSWORD = 'password';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // --- Test accounts: one per role -----------------------------------
        $admin = $this->createUser('admin@buzzer.test', 'admin', ['ROLE_ADMIN']);
        $gestionnaire = $this->createUser('manager@buzzer.test', 'manager', ['ROLE_MANAGER']);
        $parieur = $this->createUser('user@buzzer.test', 'parieur', ['ROLE_USER']);

        foreach ([$admin, $gestionnaire, $parieur] as $user) {
            $manager->persist($user);
        }

        // --- Reference data (NBA catalog) ----------------------------------
        $season = (new Season())
            ->setYear(2025)
            ->setLabel('Saison régulière 2025-26')
            ->setStartDate(new \DateTimeImmutable('2025-10-21'))
            ->setEndDate(new \DateTimeImmutable('2026-04-12'));
        $manager->persist($season);

        $lakers = $this->createTeam(1610612747, 'Los Angeles Lakers', 'LAL', 'Los Angeles', 'West', 'Pacific');
        $celtics = $this->createTeam(1610612738, 'Boston Celtics', 'BOS', 'Boston', 'East', 'Atlantic');
        $warriors = $this->createTeam(1610612744, 'Golden State Warriors', 'GSW', 'San Francisco', 'West', 'Pacific');
        $bucks = $this->createTeam(1610612749, 'Milwaukee Bucks', 'MIL', 'Milwaukee', 'East', 'Central');
        foreach ([$lakers, $celtics, $warriors, $bucks] as $team) {
            $manager->persist($team);
        }

        $lebron = $this->createPlayer(2544, 'LeBron', 'James', 'SF', $lakers);
        $tatum = $this->createPlayer(1628369, 'Jayson', 'Tatum', 'SF', $celtics);
        $curry = $this->createPlayer(201939, 'Stephen', 'Curry', 'PG', $warriors);
        $giannis = $this->createPlayer(203507, 'Giannis', 'Antetokounmpo', 'PF', $bucks);
        foreach ([$lebron, $tatum, $curry, $giannis] as $player) {
            $manager->persist($player);
        }

        // A scheduled game (open for predictions) and a finished one.
        $upcoming = $this->createGame(700001, $lakers, $celtics, $season, new \DateTimeImmutable('+7 days'), GameStatus::Scheduled);
        $finished = $this->createGame(700002, $warriors, $bucks, $season, new \DateTimeImmutable('-7 days'), GameStatus::Finished);
        $finished->setHomeScore(118)->setAwayScore(112);
        $manager->persist($upcoming);
        $manager->persist($finished);

        // --- Favorites (ManyToMany) ----------------------------------------
        $parieur->addFavoriteTeam($lakers)->addFavoriteTeam($warriors);

        // --- Social --------------------------------------------------------
        $comment = (new Comment())
            ->setAuthor($parieur)
            ->setGame($upcoming)
            ->setContent('Gros choc à venir, je vois les Lakers s\'imposer à domicile !');
        $manager->persist($comment);

        // --- Gamification --------------------------------------------------
        $badge = (new Badge())
            ->setName('Premier pronostic')
            ->setDescription('Attribué après avoir soumis son tout premier pronostic.')
            ->setThreshold(1);
        $manager->persist($badge);

        // --- League + memberships (ManyToMany with attributes) -------------
        $league = (new League())
            ->setName('Ligue des Internes')
            ->setOwner($admin)
            ->setInviteCode('BUZZER25')
            ->setIsPrivate(true);
        $manager->persist($league);

        $manager->persist($this->createMembership($admin, $league, LeagueRole::Owner, 0));
        $manager->persist($this->createMembership($parieur, $league, LeagueRole::Member, 0));

        // --- Predictions (Single Table Inheritance, one per type) ----------
        $winnerPrediction = (new MatchWinnerPrediction())
            ->setPredictedWinner($lakers);
        $winnerPrediction->setUser($parieur)->setGame($upcoming);
        $manager->persist($winnerPrediction);

        $scorePrediction = (new ScorePrediction())
            ->setPredictedHomeScore(110)
            ->setPredictedAwayScore(104);
        $scorePrediction->setUser($parieur)->setGame($upcoming);
        $manager->persist($scorePrediction);

        $propPrediction = (new PlayerPropPrediction())
            ->setPlayer($lebron)
            ->setStatType(StatType::Points)
            ->setPredictedValue(25.5)
            ->setComparison(Comparison::Over);
        $propPrediction->setUser($parieur)->setGame($upcoming);
        $manager->persist($propPrediction);

        $manager->flush();
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $email, string $username, array $roles): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setUsername($username)
            ->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::TEST_PASSWORD));

        return $user;
    }

    private function createTeam(int $apiId, string $name, string $code, string $city, string $conference, string $division): Team
    {
        return (new Team())
            ->setApiId($apiId)
            ->setName($name)
            ->setCode($code)
            ->setCity($city)
            ->setConference($conference)
            ->setDivision($division);
    }

    private function createPlayer(int $apiId, string $firstName, string $lastName, string $position, Team $team): Player
    {
        return (new Player())
            ->setApiId($apiId)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setPosition($position)
            ->setTeam($team);
    }

    private function createGame(int $apiId, Team $home, Team $away, Season $season, \DateTimeImmutable $startsAt, GameStatus $status): Game
    {
        return (new Game())
            ->setApiId($apiId)
            ->setHomeTeam($home)
            ->setAwayTeam($away)
            ->setSeason($season)
            ->setStartsAt($startsAt)
            ->setStatus($status);
    }

    private function createMembership(User $user, League $league, LeagueRole $role, int $points): LeagueMembership
    {
        return (new LeagueMembership())
            ->setUser($user)
            ->setLeague($league)
            ->setRole($role)
            ->setPoints($points);
    }
}
