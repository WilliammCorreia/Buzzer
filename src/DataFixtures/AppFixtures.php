<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Badge;
use App\Entity\Comment;
use App\Entity\Game;
use App\Entity\League;
use App\Entity\LeagueMembership;
use App\Entity\MatchWinnerPrediction;
use App\Entity\Notification;
use App\Entity\Player;
use App\Entity\PlayerPropPrediction;
use App\Entity\Prediction;
use App\Entity\ScorePrediction;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\User;
use App\Entity\UserBadge;
use App\Enum\Comparison;
use App\Enum\GameStatus;
use App\Enum\LeagueRole;
use App\Enum\NotificationType;
use App\Enum\PredictionStatus;
use App\Enum\StatType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public const TEST_PASSWORD = 'password';

    private Generator $faker;
    private Season $season;

    /** @var array<int, int> spl_object_id(user) => total points earned */
    private array $totals = [];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        $this->faker = Factory::create('fr_FR');
        $this->faker->seed(2026);
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->makeUser($manager, 'admin@buzzer.test', 'admin', ['ROLE_ADMIN']);
        $gestionnaire = $this->makeUser($manager, 'manager@buzzer.test', 'manager', ['ROLE_MANAGER']);
        $parieur = $this->makeUser($manager, 'user@buzzer.test', 'parieur', ['ROLE_USER']);
        $marie = $this->makeUser($manager, 'marie@buzzer.test', 'marie', []);
        $lucas = $this->makeUser($manager, 'lucas@buzzer.test', 'lucas', []);
        $sofia = $this->makeUser($manager, 'sofia@buzzer.test', 'sofia', []);
        $hugo = $this->makeUser($manager, 'hugo@buzzer.test', 'hugo', []);
        $lea = $this->makeUser($manager, 'lea@buzzer.test', 'lea', []);
        $noah = $this->makeUser($manager, 'noah@buzzer.test', 'noah', []);

        $this->season = (new Season())
            ->setYear(2025)
            ->setLabel('Saison régulière 2025-26')
            ->setStartDate(new \DateTimeImmutable('2025-10-21'))
            ->setEndDate(new \DateTimeImmutable('2026-04-12'));
        $manager->persist($this->season);

        /** @var array<string, Team> $t */
        $t = [];
        foreach ([
            [1610612747, 'Los Angeles Lakers', 'LAL', 'Los Angeles', 'West', 'Pacific'],
            [1610612738, 'Boston Celtics', 'BOS', 'Boston', 'East', 'Atlantic'],
            [1610612744, 'Golden State Warriors', 'GSW', 'San Francisco', 'West', 'Pacific'],
            [1610612749, 'Milwaukee Bucks', 'MIL', 'Milwaukee', 'East', 'Central'],
            [1610612743, 'Denver Nuggets', 'DEN', 'Denver', 'West', 'Northwest'],
            [1610612756, 'Phoenix Suns', 'PHX', 'Phoenix', 'West', 'Pacific'],
            [1610612748, 'Miami Heat', 'MIA', 'Miami', 'East', 'Southeast'],
            [1610612755, 'Philadelphia 76ers', 'PHI', 'Philadelphia', 'East', 'Atlantic'],
            [1610612742, 'Dallas Mavericks', 'DAL', 'Dallas', 'West', 'Southwest'],
            [1610612752, 'New York Knicks', 'NYK', 'New York', 'East', 'Atlantic'],
            [1610612760, 'Oklahoma City Thunder', 'OKC', 'Oklahoma City', 'West', 'Northwest'],
            [1610612739, 'Cleveland Cavaliers', 'CLE', 'Cleveland', 'East', 'Central'],
        ] as [$apiId, $name, $code, $city, $conf, $div]) {
            $t[$code] = $this->makeTeam($manager, $apiId, $name, $code, $city, $conf, $div);
        }

        /** @var array<int, Player> $p */
        $p = [];
        foreach ([
            'LAL' => [[2544, 'LeBron', 'James', 'SF'], [203076, 'Anthony', 'Davis', 'PF'], [1630559, 'Austin', 'Reaves', 'SG']],
            'BOS' => [[1628369, 'Jayson', 'Tatum', 'SF'], [1627759, 'Jaylen', 'Brown', 'SG'], [1628401, 'Derrick', 'White', 'PG']],
            'GSW' => [[201939, 'Stephen', 'Curry', 'PG'], [202691, 'Klay', 'Thompson', 'SG'], [203110, 'Draymond', 'Green', 'PF']],
            'MIL' => [[203507, 'Giannis', 'Antetokounmpo', 'PF'], [203081, 'Damian', 'Lillard', 'PG'], [201572, 'Brook', 'Lopez', 'C']],
            'DEN' => [[203999, 'Nikola', 'Jokic', 'C'], [1627750, 'Jamal', 'Murray', 'PG'], [203932, 'Aaron', 'Gordon', 'PF']],
            'PHX' => [[1626164, 'Devin', 'Booker', 'SG'], [201142, 'Kevin', 'Durant', 'PF'], [203078, 'Bradley', 'Beal', 'SG']],
            'MIA' => [[202710, 'Jimmy', 'Butler', 'SF'], [1628389, 'Bam', 'Adebayo', 'C'], [1629639, 'Tyler', 'Herro', 'SG']],
            'PHI' => [[203954, 'Joel', 'Embiid', 'C'], [1630178, 'Tyrese', 'Maxey', 'PG'], [202331, 'Paul', 'George', 'SF']],
            'DAL' => [[1629029, 'Luka', 'Doncic', 'PG'], [202681, 'Kyrie', 'Irving', 'SG'], [1629023, 'P.J.', 'Washington', 'PF']],
            'NYK' => [[1628973, 'Jalen', 'Brunson', 'PG'], [203944, 'Julius', 'Randle', 'PF'], [1628384, 'OG', 'Anunoby', 'SF']],
            'OKC' => [[1628983, 'Shai', 'Gilgeous-Alexander', 'SG'], [1631096, 'Chet', 'Holmgren', 'C'], [1631114, 'Jalen', 'Williams', 'SF']],
            'CLE' => [[1628378, 'Donovan', 'Mitchell', 'SG'], [1629636, 'Darius', 'Garland', 'PG'], [1630596, 'Evan', 'Mobley', 'PF']],
        ] as $code => $roster) {
            foreach ($roster as [$apiId, $fn, $ln, $pos]) {
                $p[$apiId] = $this->makePlayer($manager, $apiId, $fn, $ln, $pos, $t[$code]);
            }
        }

        // À venir (ouverts aux pronostics — dates relatives futures)
        $future = [
            'f1' => $this->makeGame($manager, 800001, $t['LAL'], $t['BOS'], '+1 day', GameStatus::Scheduled),
            'f2' => $this->makeGame($manager, 800002, $t['GSW'], $t['DEN'], '+2 days', GameStatus::Scheduled),
            'f3' => $this->makeGame($manager, 800003, $t['MIL'], $t['PHI'], '+3 days', GameStatus::Scheduled),
            'f4' => $this->makeGame($manager, 800004, $t['PHX'], $t['DAL'], '+4 days', GameStatus::Scheduled),
            'f5' => $this->makeGame($manager, 800005, $t['NYK'], $t['MIA'], '+6 days', GameStatus::Scheduled),
            'f6' => $this->makeGame($manager, 800006, $t['OKC'], $t['CLE'], '+8 days', GameStatus::Scheduled),
        ];
        // En direct
        $this->makeGame($manager, 800100, $t['DEN'], $t['LAL'], '-2 hours', GameStatus::Live, 58, 52);
        // Terminés
        $done = [
            'd1' => $this->makeGame($manager, 800101, $t['BOS'], $t['MIA'], '-1 day', GameStatus::Finished, 112, 104),
            'd2' => $this->makeGame($manager, 800102, $t['GSW'], $t['PHX'], '-2 days', GameStatus::Finished, 120, 118),
            'd3' => $this->makeGame($manager, 800103, $t['MIL'], $t['CLE'], '-3 days', GameStatus::Finished, 99, 108),
            'd4' => $this->makeGame($manager, 800104, $t['DAL'], $t['OKC'], '-5 days', GameStatus::Finished, 115, 121),
            'd5' => $this->makeGame($manager, 800105, $t['LAL'], $t['NYK'], '-7 days', GameStatus::Finished, 105, 100),
            'd6' => $this->makeGame($manager, 800106, $t['PHI'], $t['DEN'], '-9 days', GameStatus::Finished, 110, 112),
            'd7' => $this->makeGame($manager, 800107, $t['CLE'], $t['BOS'], '-11 days', GameStatus::Finished, 95, 119),
        ];
        // Terminé mais NON réglé
        $demoGame = $this->makeGame($manager, 800108, $t['MIA'], $t['GSW'], '-13 days', GameStatus::Finished, 118, 116);

        $this->makeWinner($manager, $parieur, $future['f1'], $t['LAL']);
        $this->makeScore($manager, $parieur, $future['f1'], 112, 108);
        $this->makeProp($manager, $parieur, $future['f1'], $p[2544], StatType::Points, 27.5, Comparison::Over);
        $this->makeWinner($manager, $parieur, $future['f2'], $t['GSW']);
        $this->makeWinner($manager, $parieur, $future['f3'], $t['MIL']);
        $this->makeWinner($manager, $marie, $future['f1'], $t['BOS']);
        $this->makeWinner($manager, $marie, $future['f4'], $t['PHX']);
        $this->makeWinner($manager, $lucas, $future['f2'], $t['DEN']);
        $this->makeWinner($manager, $lucas, $future['f5'], $t['NYK']);
        $this->makeWinner($manager, $sofia, $future['f3'], $t['PHI']);
        $this->makeWinner($manager, $sofia, $future['f6'], $t['OKC']);
        $this->makeScore($manager, $hugo, $future['f1'], 110, 101);
        $this->makeWinner($manager, $lea, $future['f5'], $t['MIA']);
        $this->makeProp($manager, $lea, $future['f5'], $p[202710], StatType::Points, 22.5, Comparison::Over);
        $this->makeWinner($manager, $noah, $future['f6'], $t['CLE']);

        // parieur
        $this->makeWinner($manager, $parieur, $done['d1'], $t['BOS'], 10);
        $this->makeScore($manager, $parieur, $done['d2'], 120, 118, 30);
        $this->makeWinner($manager, $parieur, $done['d3'], $t['CLE'], 10);
        $this->makeScore($manager, $parieur, $done['d5'], 105, 100, 30);
        $this->makeWinner($manager, $parieur, $done['d5'], $t['LAL'], 10);
        $this->makeWinner($manager, $parieur, $done['d7'], $t['BOS'], 10);
        $this->makeProp($manager, $parieur, $done['d1'], $p[1628369], StatType::Points, 25.5, Comparison::Over, 15);
        // admin
        $this->makeScore($manager, $admin, $done['d1'], 112, 104, 30);
        $this->makeWinner($manager, $admin, $done['d2'], $t['GSW'], 10);
        $this->makeWinner($manager, $admin, $done['d4'], $t['OKC'], 10);
        $this->makeWinner($manager, $admin, $done['d6'], $t['DEN'], 10);
        $this->makeWinner($manager, $admin, $done['d7'], $t['BOS'], 10);
        $this->makeScore($manager, $admin, $done['d7'], 95, 119, 30);
        $this->makeProp($manager, $admin, $done['d5'], $p[2544], StatType::Points, 24.5, Comparison::Over, 15);
        // marie
        $this->makeWinner($manager, $marie, $done['d1'], $t['BOS'], 10);
        $this->makeWinner($manager, $marie, $done['d2'], $t['GSW'], 10);
        $this->makeWinner($manager, $marie, $done['d3'], $t['MIL'], 0);
        $this->makeProp($manager, $marie, $done['d2'], $p[201939], StatType::Points, 23.5, Comparison::Over, 15);
        // lucas
        $this->makeWinner($manager, $lucas, $done['d4'], $t['OKC'], 10);
        $this->makeWinner($manager, $lucas, $done['d5'], $t['NYK'], 0);
        $this->makeWinner($manager, $lucas, $done['d7'], $t['BOS'], 10);
        // sofia
        $this->makeWinner($manager, $sofia, $done['d3'], $t['CLE'], 10);
        $this->makeWinner($manager, $sofia, $done['d6'], $t['DEN'], 10);
        // hugo
        $this->makeWinner($manager, $hugo, $done['d1'], $t['MIA'], 0);
        $this->makeWinner($manager, $hugo, $done['d5'], $t['LAL'], 10);
        // lea
        $this->makeWinner($manager, $lea, $done['d2'], $t['GSW'], 10);
        $this->makeWinner($manager, $lea, $done['d7'], $t['BOS'], 10);
        $this->makeProp($manager, $lea, $done['d7'], $p[1628369], StatType::Rebounds, 7.5, Comparison::Over, 15);
        // noah
        $this->makeWinner($manager, $noah, $done['d4'], $t['DAL'], 0);
        $this->makeWinner($manager, $noah, $done['d6'], $t['PHI'], 0);
        // manager
        $this->makeWinner($manager, $gestionnaire, $done['d5'], $t['LAL'], 10);
        $this->makeWinner($manager, $gestionnaire, $done['d1'], $t['BOS'], 10);

        // php bin/console app:predictions:settle
        $this->makeWinner($manager, $parieur, $demoGame, $t['MIA']);
        $this->makeScore($manager, $parieur, $demoGame, 118, 116);
        $this->makeWinner($manager, $lea, $demoGame, $t['GSW']);

        $ligueInternes = (new League())
            ->setName('Ligue des Internes')
            ->setOwner($admin)
            ->setInviteCode('BUZZER25')
            ->setIsPrivate(true);
        $manager->persist($ligueInternes);
        $this->makeMembership($manager, $admin, $ligueInternes, LeagueRole::Owner);
        foreach ([$parieur, $marie, $lucas, $sofia, $hugo] as $member) {
            $this->makeMembership($manager, $member, $ligueInternes, LeagueRole::Member);
        }

        $ligueFans = (new League())
            ->setName('NBA Fanatics')
            ->setOwner($gestionnaire)
            ->setInviteCode('NBAFANS1')
            ->setIsPrivate(false);
        $manager->persist($ligueFans);
        $this->makeMembership($manager, $gestionnaire, $ligueFans, LeagueRole::Owner);
        foreach ([$parieur, $lea, $noah, $sofia] as $member) {
            $this->makeMembership($manager, $member, $ligueFans, LeagueRole::Member);
        }

        $this->makeComment($manager, $parieur, $future['f1'], 'Gros choc pour lancer la semaine, je vois les Lakers l\'emporter à domicile !');
        $this->makeComment($manager, $marie, $future['f1'], $this->faker->sentence(12));
        $this->makeComment($manager, $lucas, $future['f2'], $this->faker->sentence(10));
        $this->makeComment($manager, $sofia, $future['f3'], $this->faker->sentence(14));
        $this->makeComment($manager, $lea, $future['f5'], $this->faker->sentence(9));
        $this->makeComment($manager, $admin, $done['d5'], 'Belle perf de LeBron hier soir, pronostic validé.');
        $this->makeComment($manager, $hugo, $future['f1'], 'SPAM — visitez mon-site-douteux.example', true);
        $this->makeComment($manager, $noah, $future['f6'], 'Message hors-sujet supprimé par la modération.', true);

        $badges = [];
        foreach ([
            ['Premier point', 'Marquez vos tout premiers points.', 1],
            ['Rookie', 'Cumulez 25 points de pronostics.', 25],
            ['All-Star', 'Cumulez 100 points de pronostics.', 100],
        ] as [$name, $description, $threshold]) {
            $badge = (new Badge())->setName($name)->setDescription($description)->setThreshold($threshold);
            $manager->persist($badge);
            $badges[] = $badge;
        }
        foreach ([$admin, $gestionnaire, $parieur, $marie, $lucas, $sofia, $hugo, $lea, $noah] as $user) {
            $total = $this->totals[spl_object_id($user)] ?? 0;
            foreach ($badges as $badge) {
                if ($total >= (int) $badge->getThreshold()) {
                    $this->makeUserBadge($manager, $user, $badge);
                }
            }
        }

        $this->makeNotification($manager, $parieur, NotificationType::Result, 'Votre pronostic « score exact » sur Warriors–Suns est gagné (+30 points) !', true);
        $this->makeNotification($manager, $parieur, NotificationType::Badge, 'Nouveau badge débloqué : « All-Star » !', false);
        $this->makeNotification($manager, $parieur, NotificationType::PredictionLock, 'Vos pronostics sur Lakers–Celtics se verrouillent bientôt.', false);
        $this->makeNotification($manager, $admin, NotificationType::Badge, 'Nouveau badge débloqué : « All-Star » !', false);
        $this->makeNotification($manager, $marie, NotificationType::LeagueInvite, 'Vous avez rejoint la ligue « Ligue des Internes ».', true);
        $this->makeNotification($manager, $lea, NotificationType::Result, 'Votre pronostic « vainqueur » sur Cavaliers–Celtics est gagné (+10 points) !', false);

        $manager->flush();
    }

    /**
     * @param list<string> $roles
     */
    private function makeUser(ObjectManager $manager, string $email, string $username, array $roles): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setUsername($username)
            ->setRoles($roles);
        $user->setIsVerified(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::TEST_PASSWORD));
        $manager->persist($user);

        return $user;
    }

    private function makeTeam(ObjectManager $manager, int $apiId, string $name, string $code, string $city, string $conference, string $division): Team
    {
        $team = (new Team())
            ->setApiId($apiId)
            ->setName($name)
            ->setCode($code)
            ->setCity($city)
            ->setConference($conference)
            ->setDivision($division)
            ->setLogoUrl(sprintf('https://cdn.nba.com/logos/nba/%d/primary/L/logo.svg', $apiId));
        $manager->persist($team);

        return $team;
    }

    private function makePlayer(ObjectManager $manager, int $apiId, string $firstName, string $lastName, string $position, Team $team): Player
    {
        $player = (new Player())
            ->setApiId($apiId)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setPosition($position)
            ->setTeam($team);
        $manager->persist($player);

        return $player;
    }

    private function makeGame(ObjectManager $manager, int $apiId, Team $home, Team $away, string $startsAt, GameStatus $status, ?int $homeScore = null, ?int $awayScore = null): Game
    {
        $game = (new Game())
            ->setApiId($apiId)
            ->setHomeTeam($home)
            ->setAwayTeam($away)
            ->setSeason($this->season)
            ->setStartsAt(new \DateTimeImmutable($startsAt))
            ->setStatus($status)
            ->setHomeScore($homeScore)
            ->setAwayScore($awayScore);
        $manager->persist($game);

        return $game;
    }

    private function makeWinner(ObjectManager $manager, User $user, Game $game, Team $team, ?int $points = null): void
    {
        $prediction = (new MatchWinnerPrediction())->setPredictedWinner($team);
        $prediction->setUser($user);
        $game->addPrediction($prediction);
        $this->applyOutcome($prediction, $user, $points);
        $manager->persist($prediction);
    }

    private function makeScore(ObjectManager $manager, User $user, Game $game, int $home, int $away, ?int $points = null): void
    {
        $prediction = (new ScorePrediction())->setPredictedHomeScore($home)->setPredictedAwayScore($away);
        $prediction->setUser($user);
        $game->addPrediction($prediction);
        $this->applyOutcome($prediction, $user, $points);
        $manager->persist($prediction);
    }

    private function makeProp(ObjectManager $manager, User $user, Game $game, Player $player, StatType $stat, float $value, Comparison $comparison, ?int $points = null): void
    {
        $prediction = (new PlayerPropPrediction())
            ->setPlayer($player)
            ->setStatType($stat)
            ->setPredictedValue($value)
            ->setComparison($comparison);
        $prediction->setUser($user);
        $game->addPrediction($prediction);
        $this->applyOutcome($prediction, $user, $points);
        $manager->persist($prediction);
    }

    private function applyOutcome(Prediction $prediction, User $user, ?int $points): void
    {
        if ($points === null) {
            $prediction->setStatus(PredictionStatus::Pending);

            return;
        }

        $prediction->setPointsAwarded($points);
        $prediction->setStatus($points > 0 ? PredictionStatus::Won : PredictionStatus::Lost);
        $this->totals[spl_object_id($user)] = ($this->totals[spl_object_id($user)] ?? 0) + $points;
    }

    private function makeMembership(ObjectManager $manager, User $user, League $league, LeagueRole $role): void
    {
        $membership = (new LeagueMembership())
            ->setUser($user)
            ->setLeague($league)
            ->setRole($role)
            ->setPoints($this->totals[spl_object_id($user)] ?? 0);
        $manager->persist($membership);
    }

    private function makeComment(ObjectManager $manager, User $author, Game $game, string $content, bool $hidden = false): void
    {
        $comment = (new Comment())
            ->setAuthor($author)
            ->setGame($game)
            ->setContent($content)
            ->setIsHidden($hidden);
        $manager->persist($comment);
    }

    private function makeUserBadge(ObjectManager $manager, User $user, Badge $badge): void
    {
        $manager->persist((new UserBadge())->setUser($user)->setBadge($badge));
    }

    private function makeNotification(ObjectManager $manager, User $user, NotificationType $type, string $message, bool $read): void
    {
        $manager->persist(
            (new Notification())
                ->setRecipient($user)
                ->setType($type)
                ->setMessage($message)
                ->setIsRead($read)
        );
    }
}
