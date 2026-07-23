<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleSessionGarbageCollectionTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testGarbageCollectionRemovesExpiredSessions(): void
    {
        $envs = [
            'APP_ENV' => 'session_gc',
            'COOKIE_LIFETIME' => '1',
            'SESSION_GC_PROBABILITY' => '100',
            'SESSION_GC_DIVISOR' => '100',
        ];
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9998',
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            // Create initial session via client1
            $client1 = HttpClient::fromDomain('localhost', 9998, false);
            $this->assertTrue($client1->connect(3, 1, true));

            $response1 = $client1->send('/session/gc-test')['response'];
            $this->assertSame(200, $response1['statusCode']);

            // Verify 1 session in table
            $countBefore = $client1->send('/session/gc-count')['response'];
            $this->assertSame(200, $countBefore['statusCode']);
            $this->assertSame(1, $countBefore['body']['count'], 'One session in table before GC trigger');

            // Wait for the 1-second session TTL to expire
            sleep(2);

            // Use a fresh client (no cookies) to trigger GC without refreshing the expired session
            $client2 = HttpClient::fromDomain('localhost', 9998, false);
            $this->assertTrue($client2->connect(3, 1, true));
            $client2->send('/session/gc-test');

            // GC (100% probability) should have removed the expired session
            $countAfter = $client2->send('/session/gc-count')['response'];
            $this->assertSame(200, $countAfter['statusCode']);
            $this->assertSame(1, $countAfter['body']['count'], 'GC removed expired session; only new session remains');
        });
    }

    public function testGarbageCollectionDoesNotRunWhenProbabilityIsZero(): void
    {
        $envs = [
            'APP_ENV' => 'session_gc',
            'COOKIE_LIFETIME' => '1',
            'SESSION_GC_PROBABILITY' => '0',
            'SESSION_GC_DIVISOR' => '100',
        ];
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9997',
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            // Create initial session via client1
            $client1 = HttpClient::fromDomain('localhost', 9997, false);
            $this->assertTrue($client1->connect(3, 1, true));

            $response1 = $client1->send('/session/gc-test')['response'];
            $this->assertSame(200, $response1['statusCode']);

            // Verify 1 session in table
            $countBefore = $client1->send('/session/gc-count')['response'];
            $this->assertSame(1, $countBefore['body']['count'], 'One session in table before trigger');

            // Wait for the 1-second session TTL to expire
            sleep(2);

            // Use a fresh client (no cookies) so the expired session is not refreshed
            $client2 = HttpClient::fromDomain('localhost', 9997, false);
            $this->assertTrue($client2->connect(3, 1, true));
            $client2->send('/session/gc-test');

            // GC did not run (probability=0); expired session (id1) still in table alongside new one (id2)
            $countAfter = $client2->send('/session/gc-count')['response'];
            $this->assertSame(
                2,
                $countAfter['body']['count'],
                'Expired session not cleaned; two sessions in table when GC is disabled'
            );
        });
    }
}
