<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleServerConfigurationTest extends ServerTestCase
{
    protected function setUp(): void
    {
        $this->markTestSkippedIfXdebugEnabled();
        $this->deleteVarDirectory();
    }

    public function testConfigurationData(): void
    {
        $serverStart = $this->createConsoleProcess(
            [
                'swoole:server:start',
                '--host=localhost',
                '--port=9999',
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

            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect());
            /** @var array{
             *    body: array{
             *     upload_tmp_dir: string
             *    }
             *  } $response
             */
            $response = $client->send('/settings')['response'];
            $this->assertEquals(
                '/usr/src/app/tests/Fixtures/Symfony/app/public/uploads',
                $response['body']['upload_tmp_dir'],
            );
        });
    }
}
