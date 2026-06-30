<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional smoke tests: the public pages boot and render successfully.
 */
final class SmokeTest extends WebTestCase
{
    public function testHomePageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Buzzer');
    }

    public function testLoginPageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="email"]');
        self::assertSelectorExists('input[name="password"]');
    }

    public function testCalendarPageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/matchs');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Calendrier');
    }
}
