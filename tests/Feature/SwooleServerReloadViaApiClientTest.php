<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Server\Api\ApiServerClientFactory;
use SwooleBundle\SwooleBundle\Server\Config\Socket;
use SwooleBundle\SwooleBundle\Server\Config\Sockets;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ReplacedContentController;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleServerReloadViaApiClientTest extends ServerTestCase
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
        self::bootKernel();
        /** @var Sockets $sockets */
        $sockets = self::getContainer()->get(Sockets::class);
        $sockets->changeApiSocket(new Socket('0.0.0.0', self::port(1)));
        /** @var ApiServerClientFactory $apiClientFactory */
        $apiClientFactory = self::getContainer()->get(ApiServerClientFactory::class);
        $apiClient = $apiClientFactory->newClient();

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

        self::assertTrue($serverStart->isSuccessful());

        $this->runAsCoroutineAndWait(function () use ($apiClient): void {
            $this->deferServerStop();
            $this->deferRestoreOriginalTemplateControllerResponse();

            $serverClient = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($serverClient->connect(self::connectTimeout(), waitIfNoConnection: true));

            $response1 = $serverClient->send($this->replacedContentRoute())['response'];

            $this->assertSame(200, $response1['statusCode']);
            $this->assertSame('Wrong response!', $response1['body']);

            $expectedResponse = 'Hello world from reloaded server worker via HTTP API!';
            $this->replaceContentInTestController($expectedResponse);
            $this->assertTestControllerResponseEquals($expectedResponse);

            $response2 = $serverClient->send($this->replacedContentRoute())['response'];

            $this->assertSame(200, $response2['statusCode']);
            $this->assertNotSame($expectedResponse, $response2['body']);

            $apiClient->reload();
            Coroutine::sleep(self::coverageEnabled() ? 3 : 1);

            $response3 = $serverClient->send($this->replacedContentRoute())['response'];

            $this->assertSame(200, $response3['statusCode']);
            $this->assertSame($expectedResponse, $response3['body']);
        });
    }

    public function testStartRequestApiToReloadCallStopUsingApiEnv(): void
    {
        self::bootKernel(['environment' => 'api']);
        /** @var ApiServerClientFactory $apiClientFactory */
        $apiClientFactory = self::getContainer()->get(ApiServerClientFactory::class);
        $apiClient = $apiClientFactory->newClient();

        $envs = ['APP_ENV' => 'api'];
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        self::assertTrue($serverStart->isSuccessful());

        $this->runAsCoroutineAndWait(function () use ($apiClient, $envs): void {
            $this->deferServerStop([], $envs);
            $this->deferRestoreOriginalTemplateControllerResponse();

            $serverClient = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($serverClient->connect(self::connectTimeout(), waitIfNoConnection: true));

            $response1 = $serverClient->send($this->replacedContentRoute())['response'];

            $this->assertSame(200, $response1['statusCode']);
            $this->assertSame('Wrong response!', $response1['body']);

            $expectedResponse = 'Hello world from reloaded server worker via HTTP API!';
            $this->replaceContentInTestController($expectedResponse);
            $this->assertTestControllerResponseEquals($expectedResponse);

            $response2 = $serverClient->send($this->replacedContentRoute())['response'];

            $this->assertSame(200, $response2['statusCode']);
            $this->assertNotSame($expectedResponse, $response2['body']);

            $apiClient->reload();
            Coroutine::sleep(self::coverageEnabled() ? 3 : 1);

            $response3 = $serverClient->send($this->replacedContentRoute())['response'];

            $this->assertSame(200, $response3['statusCode']);
            $this->assertSame($expectedResponse, $response3['body']);
        });
    }
}
