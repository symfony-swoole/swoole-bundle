<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SymfonyAccessLogEventTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testAccessLogEventLogsRequestTest(): void
    {
        $envs = ['APP_ENV' => 'access_log'];
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

        $this->assertStringContainsString(
            'swoole.access_log.INFO: 127.0.0.1 - -',
            file_get_contents($this->getVarDirectoryPath() . '/log/access_log.log')
        );
    }
}
