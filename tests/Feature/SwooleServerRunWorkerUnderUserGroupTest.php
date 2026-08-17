<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleServerRunWorkerUnderUserGroupTest extends ServerTestCase
{
    public function testRunWorkerUnderCertainUserAndGroup(): void
    {
        $env = 'user_group';
        $envs = ['APP_ENV' => $env];
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->enableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);
        $this->assertStringContainsString('user_test:group_test', $serverStart->getOutput());

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(), waitIfNoConnection: true));
            $this->assertHelloWorldRequestSucceeded($client);
        });
    }
}
