<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SymfonySessionSwooleSessionIdStorageTest extends ServerTestCase
{
    protected function setUp(): void
    {
        $this->markTestSkippedIfXdebugEnabled();
        $this->deleteVarDirectory();
    }

    public function testReturnTheSameDataForTheSameSessionId(): void
    {
        $cookieLifetime = 5;
        $envs = [
            'APP_ENV' => 'session',
            'COOKIE_LIFETIME' => $cookieLifetime,
        ];
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect(3, 1, true));

            $response1 = $client->send('/session/1')['response'];
            $this->assertSame(200, $response1['statusCode']);
            $this->assertArrayHasKey('set-cookie', $response1['headers']);
            $this->assertArrayHasKey('SWOOLESSID', $response1['cookies']);
            $sessionId1 = $response1['cookies']['SWOOLESSID'];
            $body1 = $response1['body'];

            $response2 = $client->send('/session/2')['response'];
            $this->assertArrayHasKey('SWOOLESSID', $response2['cookies']);
            $sessionId2 = $response2['cookies']['SWOOLESSID'];
            $body2 = $response2['body'];

            $this->assertSame($sessionId1, $sessionId2);
            $this->assertSame($body1, $body2);
        });
    }

    public function testDoNotReturnTheSameSessionForDifferentClients(): void
    {
        $cookieLifetime = 5;
        $envs = [
            'APP_ENV' => 'session',
            'COOKIE_LIFETIME' => $cookieLifetime,
        ];
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client1 = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client1->connect(3, 1, true));

            $response1 = $client1->send('/session/1')['response'];
            $this->assertArrayHasKey('SWOOLESSID', $response1['cookies']);
            $sessionId1 = $response1['cookies']['SWOOLESSID'];
            $body1 = $response1['body'];

            $client2 = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client2->connect());

            $response2 = $client2->send('/session/2')['response'];
            $this->assertArrayHasKey('SWOOLESSID', $response2['cookies']);
            $sessionId2 = $response2['cookies']['SWOOLESSID'];
            $body2 = $response2['body'];

            $this->assertNotSame($sessionId1, $sessionId2);
            $this->assertNotSame($body1, $body2);
        });
    }

    public function testExpireSession(): void
    {
        $cookieLifetime = 1;
        $envs = [
            'APP_ENV' => 'session',
            'COOKIE_LIFETIME' => $cookieLifetime,
        ];
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($cookieLifetime, $envs): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect(3, 1, true));

            $response1 = $client->send('/session/1')['response'];
            $this->assertSame(200, $response1['statusCode']);
            $this->assertArrayHasKey('SWOOLESSID', $response1['cookies']);

            $sessionId1 = $response1['cookies']['SWOOLESSID'];
            $setCookieHeader1 = $response1['headers']['set-cookie'];
            $body1 = $response1['body'];

            Coroutine::sleep($cookieLifetime + 1);

            $response2 = $client->send('/session/2')['response'];
            $this->assertSame(200, $response2['statusCode']);
            $this->assertArrayHasKey('SWOOLESSID', $response2['cookies']);

            $sessionId2 = $response2['cookies']['SWOOLESSID'];
            $setCookieHeader2 = $response2['headers']['set-cookie'];
            $body2 = $response2['body'];

            $this->assertNotSame($sessionId1, $sessionId2);
            $this->assertNotSame($setCookieHeader1, $setCookieHeader2);
            $this->assertNotSame($body1, $body2);
        });
    }

    public function testUpdateSession(): void
    {
        $cookieLifetime = 5;
        $envs = [
            'APP_ENV' => 'session',
            'COOKIE_LIFETIME' => $cookieLifetime,
        ];
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect(3, 1, true));

            $response1 = $client->send('/session/1')['response'];
            $this->assertSame(200, $response1['statusCode']);
            $this->assertArrayHasKey('SWOOLESSID', $response1['cookies']);
            $sessionId1 = $response1['cookies']['SWOOLESSID'];
            $setCookieHeader1 = $response1['headers']['set-cookie'];
            $body1 = $response1['body'];

            Coroutine::sleep(2);

            $response2 = $client->send('/session/2')['response'];
            $this->assertSame(200, $response2['statusCode']);
            $this->assertArrayHasKey('SWOOLESSID', $response2['cookies']);

            $sessionId2 = $response2['cookies']['SWOOLESSID'];

            $this->assertNotNull($response2['headers']['set-cookie']);

            $setCookieHeader2 = $response2['headers']['set-cookie'];
            $body2 = $response2['body'];

            $this->assertSame($sessionId1, $sessionId2);
            $this->assertSame($body1, $body2);
            $this->assertNotSame($setCookieHeader1, $setCookieHeader2);
        });
    }

    public function testDoNotReturnTheSameSessionForDifferentClientsWithHttpCacheEnabled(): void
    {
        $cookieLifetime = 5;
        $envs = [
            'APP_ENV' => 'session_http_cache',
            'COOKIE_LIFETIME' => $cookieLifetime,
            // Only one worker to reliably verify app state is reset between requests.
            // Without it 2nd request may be handled by a different "clean" worker, which would distort test results.
            'WORKER_COUNT' => 1,
        ];
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client1 = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client1->connect(3, 1, true));

            $response1 = $client1->send('/session/1')['response'];
            $this->assertArrayHasKey('SWOOLESSID', $response1['cookies']);
            $sessionId1 = $response1['cookies']['SWOOLESSID'];
            $body1 = $response1['body'];

            $client2 = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client2->connect());

            $response2 = $client2->send('/session/2')['response'];
            $this->assertArrayHasKey('SWOOLESSID', $response2['cookies']);
            $sessionId2 = $response2['cookies']['SWOOLESSID'];
            $body2 = $response2['body'];

            $this->assertNotSame($sessionId1, $sessionId2);
            $this->assertNotSame($body1, $body2);
        });
    }
}
