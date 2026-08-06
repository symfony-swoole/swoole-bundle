<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SymfonyEventsTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testLifecycleEventsInWorkersAreCaughtBySymfony(): void
    {
        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], ['APP_ENV' => 'prod']);

        $serverRun->setTimeout(10);
        $serverRun->start();

        $this->runAsCoroutineAndWait(static function (): void {
            $client = HttpClient::fromDomain('localhost', self::port(), false);
            self::assertTrue($client->connect(self::connectTimeout(), 1, true));

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
