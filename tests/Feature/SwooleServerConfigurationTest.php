<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleServerConfigurationTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testConfigurationData(): void
    {
        $serverStart = $this->createConsoleProcess(
            [
                'swoole:server:start',
                '--host=localhost',
                sprintf('--port=%d', self::port()),
            ],
            [
                'APP_ENV' => 'settings',
            ]
        );

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function (): void {
            $this->deferServerStop();

            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(), waitIfNoConnection: true));
            /** @var array{
             *    body: array{
             *      server: array{
             *        upload_tmp_dir: string,
             *        dispatch_mode: int
             *      }
             *    }
             *  } $response
             */
            $response = $client->send('/settings')['response'];
            // Where the fixture app lives, rather than where it lives in the CI image: the setting is
            // `kernel.project_dir` joined with a path, and the project dir is TestAppKernel's own
            // directory - a checkout anywhere else reports its own.
            $this->assertEquals(
                dirname(__DIR__) . '/Fixtures/Symfony/app/public/uploads',
                $response['body']['server']['upload_tmp_dir'],
            );
            $this->assertSame(3, $response['body']['server']['dispatch_mode']);
        });
    }
}
