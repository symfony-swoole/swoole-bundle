<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SymfonyHttpRequestContainsRequestUriTest extends ServerTestCase
{
    protected function setUp(): void
    {
        $this->markTestSkippedIfXdebugEnabled();
        $this->deleteVarDirectory();
    }

    /*
     * Test whether current Symfony's Request->getRequestUri() is working
     * @see https://github.com/k911/swoole-bundle/issues/268
     */
    public function testWhetherCurrentSymfonyHttpRequestContainsRequestUri(): void
    {
        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            '--port=9999',
        ]);

        $serverRun->setTimeout(10);
        $serverRun->start();

        $this->runAsCoroutineAndWait(function (): void {
            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect(3, 1, true));

            $uri = '/http/request/uri?test1=1&test2=test3';
            $response = $client->send('/http/request/uri?test1=1&test2=test3')['response'];

            $this->assertSame(200, $response['statusCode']);
            $this->assertSame([
                'requestUri' => $uri,
            ], $response['body']);
        });

        $serverRun->stop();
    }

    /*
     * Test whether current Symfony's Request->getRequestUri() is working
     * @see https://github.com/k911/swoole-bundle/issues/268
     */
    public function testWhetherCurrentSymfonyHttpRequestContainsRequestUriInStreamedResponse(): void
    {
        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            '--port=9999',
        ]);

        $serverRun->setTimeout(10);
        $serverRun->start();

        $this->runAsCoroutineAndWait(function (): void {
            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect(3, 1, true));

            $uri = '/http/request/streamed-uri?test1=1&test2=test3';
            $response = $client->send('/http/request/streamed-uri?test1=1&test2=test3')['response'];

            $this->assertSame(200, $response['statusCode']);
            $this->assertSame([
                'requestUri' => $uri,
            ], $response['body']);
        });

        $serverRun->stop();
    }
}
