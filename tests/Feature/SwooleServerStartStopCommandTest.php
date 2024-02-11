<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use co;
use SwooleBundle\SwooleBundle\Client\Exception\ClientConnectionErrorException;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleServerStartStopCommandTest extends ServerTestCase
{
    protected function setUp(): void
    {
        $this->markTestSkippedIfXdebugEnabled();
        $this->deleteVarDirectory();
    }

    public function testStartCallStop(): void
    {
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ]);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function (): void {
            $this->deferServerStop();

            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect());
            $this->assertHelloWorldRequestSucceeded($client);
        });
    }

    public function testStartCallStopOnReactorRunningMode(): void
    {
        $envs = ['APP_ENV' => 'reactor'];
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
            $this->assertTrue($client->connect());
            $this->assertHelloWorldRequestSucceeded($client);
        });
    }

    public function testNoDelayShutdown(): void
    {
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ]);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function (): void {
            go(function (): void {
                $client = HttpClient::fromDomain('localhost', 9999, false);
                $this->assertTrue($client->connect());

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
                co::sleep(1);
                $this->serverStop(['--no-delay']);
            });
        });
    }
}
