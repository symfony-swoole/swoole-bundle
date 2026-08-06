<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\Http;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ReplacedContentController;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleServerReloadViaHttpApiTest extends ServerTestCase
{
    use ReplacedContentController;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
        $this->writeOriginalTestController();
    }

    public function testStartRequestApiToReloadCallStop(): void
    {
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
            '--api',
            sprintf('--api-port=%d', self::port(1)),
        ]);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function (): void {
            $this->deferServerStop();
            $this->deferRestoreOriginalTemplateControllerResponse();

            $serverClient = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($serverClient->connect(self::connectTimeout(), 1, true));

            $response1 = $serverClient->send($this->replacedContentRoute())['response'];

            $this->assertSame(200, $response1['statusCode']);
            $this->assertSame('Wrong response!', $response1['body']);

            $expectedResponse = 'Hello world from reloaded server worker via HTTP API!';
            $this->replaceContentInTestController($expectedResponse);
            $this->assertTestControllerResponseEquals($expectedResponse);

            $response2 = $serverClient->send($this->replacedContentRoute())['response'];

            $this->assertSame(200, $response2['statusCode']);
            $this->assertNotSame($expectedResponse, $response2['body']);

            $apiClient = HttpClient::fromDomain('localhost', self::port(1), false);
            $this->assertTrue($apiClient->connect(self::connectTimeout(), 1, true));

            $apiClientResponse = $apiClient->send('/api/server', Http::METHOD_PATCH)['response'];
            $this->assertSame(204, $apiClientResponse['statusCode']);
            Coroutine::sleep(self::coverageEnabled() ? 3 : 1);

            $response3 = $serverClient->send($this->replacedContentRoute())['response'];

            $this->assertSame(200, $response3['statusCode']);
            $this->assertSame($expectedResponse, $response3['body']);
        });
    }
}
