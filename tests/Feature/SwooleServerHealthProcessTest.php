<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use ArrayObject;
use Override;
use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleServerHealthProcessTest extends ServerTestCase
{
    private const int APP_PORT = 9999;
    private const int HEALTH_PORT = 9997;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
        $this->killAllProcessesListeningOnPort(self::HEALTH_PORT);
    }

    public function testHealthEndpointRespondsWhileEveryWorkerIsBlocked(): void
    {
        $envs = ['APP_ENV' => self::resolveEnvironment('health')];
        $this->startServer($envs);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $appClient = HttpClient::fromDomain('localhost', self::APP_PORT, false);
            $this->assertTrue($appClient->connect(3, 1, true));

            $blocking = new ArrayObject(['finished' => false]);
            $waitGroup = $this->getSwoole()->waitGroup();

            go(function () use ($waitGroup, $blocking): void {
                $waitGroup->add();
                $client = HttpClient::fromDomain('localhost', self::APP_PORT, false, ['timeout' => 15]);
                $response = $client->send('/test/blocking/5000', timeout: 15)['response'];

                $this->assertSame(200, $response['statusCode']);
                $blocking['finished'] = true;
                $waitGroup->done();
            });

            Coroutine::sleep(1);

            $startedAt = microtime(true);
            $response = $this->sendHealthRequest(self::HEALTH_PORT, '/healthz');
            $elapsed = microtime(true) - $startedAt;

            $this->assertFalse(
                $blocking['finished'],
                'The blocking request must still be occupying the worker while the health endpoint is probed.',
            );
            $this->assertSame(200, $response['statusCode']);
            $this->assertSame(['ok' => true], $response['body']);
            $this->assertLessThan(
                1.0,
                $elapsed,
                'Health endpoint must answer without queueing behind the blocked worker pool.',
            );

            $waitGroup->wait();
            $this->assertTrue($blocking['finished']);
        });
    }

    public function testHealthEndpointSurvivesAClientThatSendsNothing(): void
    {
        $envs = ['APP_ENV' => self::resolveEnvironment('health')];
        $this->startServer($envs);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $this->assertTrue($this->awaitHealthEndpoint(self::HEALTH_PORT));
            $this->assertSame(200, $this->sendHealthRequest(self::HEALTH_PORT, '/healthz')['statusCode']);

            $silent = stream_socket_client(sprintf('tcp://localhost:%d', self::HEALTH_PORT));
            $this->assertNotFalse($silent, 'Could not open the silent connection.');

            $response = $this->sendHealthRequest(self::HEALTH_PORT, '/healthz', 5);

            $this->assertSame(200, $response['statusCode']);
            $this->assertSame(['ok' => true], $response['body']);

            fclose($silent);
        });
    }

    public function testHealthEndpointIsServedOnTheConfiguredPath(): void
    {
        $envs = ['APP_ENV' => self::resolveEnvironment('health_custom_path')];
        $this->startServer($envs);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            // The health process binds its socket after the start command has returned.
            $this->assertTrue($this->awaitHealthEndpoint(self::HEALTH_PORT));

            $configured = $this->sendHealthRequest(self::HEALTH_PORT, '/alive');
            $this->assertSame(200, $configured['statusCode']);
            $this->assertSame(['ok' => true], $configured['body']);

            $default = $this->sendHealthRequest(self::HEALTH_PORT, '/healthz');
            $this->assertSame(404, $default['statusCode']);
        });
    }

    /**
     * @param array<string, string> $envs
     */
    private function startServer(array $envs): void
    {
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::APP_PORT),
        ], $envs);

        $serverStart->setTimeout(5);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);
    }
}
