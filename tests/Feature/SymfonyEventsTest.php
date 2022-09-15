<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Feature;

use K911\Swoole\Client\HttpClient;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SymfonyEventsTest extends ServerTestCase
{
    protected function setUp(): void
    {
        $this->markTestSkippedIfXdebugEnabled();
    }

    public function testLifecycleEventsInWorkersAreCaughtBySymfony(): void
    {
        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            '--port=9999',
        ], ['APP_ENV' => 'prod']);

        $serverRun->setTimeout(10);
        $serverRun->start();

        $this->runAsCoroutineAndWait(function (): void {
            $client = HttpClient::fromDomain('localhost', 9999, false);
            self::assertTrue($client->connect());

            $response = $client->send('/list-events')['response'];
            $data = $response['body'];

            self::assertSame(200, $response['statusCode']);
            self::assertSame(
                [
                    'serverStarted' => false,
                    'workerStarted' => true,
                ],
                $data
            );
        });

        $serverRun->stop();
    }
}
