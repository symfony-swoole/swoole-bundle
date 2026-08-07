<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleServerCompressionTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testEnabledCompression(): void
    {
        $serverStart = $this->createConsoleProcess(
            [
                'swoole:server:start',
                '--host=localhost',
                sprintf('--port=%d', self::port()),
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

            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(), waitIfNoConnection: true));
            /** @var array{
             *    body: array{
             *      server: array{
             *        http_compression: bool,
             *        http_compression_level: int
             *      }
             *    }
             *  } $response
             */
            $response = $client->send('/settings')['response'];
            $this->assertTrue($response['body']['server']['http_compression']);
            $this->assertEquals(
                4,
                $response['body']['server']['http_compression_level'],
            );
        });
    }

    public function testCompressionIsDisabledByDefault(): void
    {
        $serverStart = $this->createConsoleProcess(
            [
                'swoole:server:start',
                '--host=localhost',
                sprintf('--port=%d', self::port()),
            ],
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
             *   body: array{
             *     server: array{
             *       http_compression: bool,
             *       http_compression_level: int
             *     }
             *   }
             * } $response
             */
            $response = $client->send('/settings')['response'];
            $this->assertFalse($response['body']['server']['http_compression']);
            $this->assertEquals(
                4,
                $response['body']['server']['http_compression_level'],
            );
        });
    }
}
