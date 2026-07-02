<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\League;
use App\Entity\LeagueMembership;
use App\Entity\User;
use App\Enum\LeagueRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LeagueFlowTest extends WebTestCase
{
    public function testUserCanJoinLeagueByCodeAndSeesLeaderboardEntry(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        // Fresh, collision-free data for this run.
        $suffix = uniqid();
        $owner = (new User())
            ->setEmail('owner_'.$suffix.'@buzzer.test')
            ->setUsername('o'.substr($suffix, -6))
            ->setPassword('not-used-here');
        $member = (new User())
            ->setEmail('member_'.$suffix.'@buzzer.test')
            ->setUsername('m'.substr($suffix, -6))
            ->setPassword('not-used-here');
        $league = (new League())
            ->setName('Ligue '.$suffix)
            ->setOwner($owner)
            ->setInviteCode(strtoupper(substr($suffix, -10)))
            ->setIsPrivate(false);
        $ownerMembership = (new LeagueMembership())
            ->setUser($owner)
            ->setLeague($league)
            ->setRole(LeagueRole::Owner)
            ->setPoints(50);

        foreach ([$owner, $member, $league, $ownerMembership] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $client->loginUser($member);

        $crawler = $client->request('GET', '/leagues/join');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->form([
            'join_league[inviteCode]' => $league->getInviteCode(),
        ]);
        $client->submit($form);

        self::assertResponseRedirects();
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', $member->getUsername());
    }
}
