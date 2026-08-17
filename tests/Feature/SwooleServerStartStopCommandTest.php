<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Co;
use Override;
use SwooleBundle\SwooleBundle\Client\Exception\ClientConnectionErrorException;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleServerStartStopCommandTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testStartCallStop(): void
    {
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ]);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function (): void {
            $this->deferServerStop();

            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(), waitIfNoConnection: true));
            $this->assertHelloWorldRequestSucceeded($client);
        });
    }

    public function testStartCallStopOnReactorRunningMode(): void
    {
        $envs = ['APP_ENV' => 'reactor'];
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(), waitIfNoConnection: true));
            $this->assertHelloWorldRequestSucceeded($client);
        });
    }

    public function testNoDelayShutdown(): void
    {
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ]);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function (): void {
            go(function (): void {
                sleep(1); // weird behaviour with swoole, connections need some "boot" time
                $client = HttpClient::fromDomain('localhost', self::port(), false);
                $this->assertTrue($client->connect(self::connectTimeout(), waitIfNoConnection: true));

                try {
                    $response = $client->send('/dummy-sleep')['response'];
                    $this->assertSame(200, $response['statusCode']);
                    $this->fail('Server was not shutdown by kill (no-delay).');
                } catch (ClientConnectionErrorException $e) {
                    // exception thrown, request was not finished, no-delay server shutdown
                    $this->assertStringContainsStringIgnoringCase('Server Reset', $e->getMessage());
                }
            });
            go(function (): void {
                // wait for $client to do request
                Co::sleep(1);
                $this->serverStop(['--no-delay']);
            });
        });
    }
}
