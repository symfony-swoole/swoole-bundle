<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleServerCompressionTest extends ServerTestCase
{
    protected function setUp(): void
    {
        $this->markTestSkippedIfXdebugEnabled();
        $this->deleteVarDirectory();
    }

    public function testEnabledCompression(): void
    {
        $serverStart = $this->createConsoleProcess(
            [
                'swoole:server:start',
                '--host=localhost',
                '--port=9999',
            ],
            [
                'APP_ENV' => 'compression',
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
             *     http_compression: bool,
             *     http_compression_level: int
             *    }
             *  } $response
             */
            $response = $client->send('/settings')['response'];
            $this->assertTrue($response['body']['http_compression']);
            $this->assertEquals(
                4,
                $response['body']['http_compression_level'],
            );
        });
    }

    public function testCompressionIsDisabledByDefault(): void
    {
        $serverStart = $this->createConsoleProcess(
            [
                'swoole:server:start',
                '--host=localhost',
                '--port=9999',
            ],
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
             *     http_compression: bool,
             *     http_compression_level: int
             *    }
             *  } $response
             */
            $response = $client->send('/settings')['response'];
            $this->assertFalse($response['body']['http_compression']);
            $this->assertEquals(
                4,
                $response['body']['http_compression_level'],
            );
        });
    }
}
