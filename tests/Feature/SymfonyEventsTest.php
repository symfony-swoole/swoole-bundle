<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SymfonyEventsTest extends ServerTestCase
{
    protected function setUp(): void
    {
        $this->markTestSkippedIfXdebugEnabled();
        $this->deleteVarDirectory();
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
            self::assertTrue($client->connect(3, 1, true));

            $response = $client->send('/list-events')['response'];
            $data = $response['body'];

            self::assertSame(200, $response['statusCode']);
            self::assertSame(
                [
                    'serverStarted' => false,
                    'workerStarted' => true,
                    'workerStopped' => false,
                    'workerExited' => false,
                    'workerError' => false,
                ],
                $data
            );
        });

        $serverRun->stop();
    }
}
