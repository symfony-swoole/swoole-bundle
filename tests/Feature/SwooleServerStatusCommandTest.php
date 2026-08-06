<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class SwooleServerStatusCommandTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testCheckServerStatusViaProcess(): void
    {
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
            '--api',
            sprintf('--api-port=%d', self::port(1)),
        ]);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function (): void {
            $this->deferServerStop();

            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(), 1, true));

            $serverStatus = $this->createConsoleProcess([
                'swoole:server:status',
                '--api-host=localhost',
                sprintf('--api-port=%d', self::port(1)),
            ]);

            $serverStatus->setTimeout(3);
            $serverStatus->run();

            $this->assertProcessSucceeded($serverStatus);

            $this->assertStringContainsString('Fetched status', $serverStatus->getOutput());
            $this->assertStringContainsString('Fetched metrics', $serverStatus->getOutput());
            $this->assertStringContainsString('Listener[0] Host', $serverStatus->getOutput());
            $this->assertStringContainsString('Requests', $serverStatus->getOutput());

            $this->assertHelloWorldRequestSucceeded($client);
        });
    }

    public function testCheckServerStatusViaCommandTester(): void
    {
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
            '--api',
            sprintf('--api-port=%d', self::port(1)),
        ]);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $kernel = self::createKernel();
        $application = new Application($kernel);
        $command = $application->find('swoole:server:status');
        $commandTester = new CommandTester($command);

        $this->runAsCoroutineAndWait(function () use ($commandTester): void {
            $this->deferServerStop();

            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(), 1, true));

            $commandTester->execute([
                'command' => 'swoole:server:status',
                '--api-host' => 'localhost',
                '--api-port' => (string) self::port(1),
            ]);

            $this->assertSame(0, $commandTester->getStatusCode());
            $this->assertHelloWorldRequestSucceeded($client);
        });

        $output = $commandTester->getDisplay();

        self::assertStringContainsString('Fetched status', $output);
        self::assertStringContainsString('Fetched metrics', $output);
        self::assertStringContainsString('Listener[0] Host', $output);
        self::assertStringContainsString('Requests', $output);
    }

    public function testCheckServerStatusFailWhenServerNotRunning(): void
    {
        $kernel = self::createKernel();
        $application = new Application($kernel);
        $command = $application->find('swoole:server:status');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'command' => 'swoole:server:status',
            '--api-host' => 'localhost',
            '--api-port' => (string) self::port(1),
        ]);

        self::assertSame(1, $commandTester->getStatusCode());
        $this->assertCommandTesterDisplayContainsString(
            'An error occurred while connecting to the API Server. Please verify configuration.',
            $commandTester
        );
    }
}
