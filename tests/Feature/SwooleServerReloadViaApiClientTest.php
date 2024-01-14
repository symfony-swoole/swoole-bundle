<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Server\Api\ApiServerClientFactory;
use SwooleBundle\SwooleBundle\Server\Config\Socket;
use SwooleBundle\SwooleBundle\Server\Config\Sockets;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleServerReloadViaApiClientTest extends ServerTestCase
{
    private const CONTROLLER_TEMPLATE_ORIGINAL_TEXT = 'Wrong response!';
    private const CONTROLLER_TEMPLATE_REPLACE_TEXT = '%REPLACE%';
    private const CONTROLLER_TEMPLATE_SRC = __DIR__.'/../Fixtures/Symfony/TestBundle/Controller/ReplacedContentTestController.php.tmpl';
    private const CONTROLLER_TEMPLATE_DEST = __DIR__.'/../Fixtures/Symfony/TestBundle/Controller/ReplacedContentTestController.php';

    protected function setUp(): void
    {
        $this->markTestSkippedIfXdebugEnabled();
        $this->deleteVarDirectory();
    }

    public function testStartRequestApiToReloadCallStop(): void
    {
        static::bootKernel();
        $sockets = static::getContainer()->get(Sockets::class);
        $sockets->changeApiSocket(new Socket('0.0.0.0', 9998));
        $apiClient = static::getContainer()->get(ApiServerClientFactory::class)
            ->newClient()
        ;

        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
            '--api',
            '--api-port=9998',
        ]);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        self::assertTrue($serverStart->isSuccessful());

        $this->runAsCoroutineAndWait(function () use ($apiClient): void {
            $this->deferServerStop();
            $this->deferRestoreOriginalTemplateControllerResponse();

            $serverClient = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($serverClient->connect());

            $response1 = $serverClient->send('/test/replaced/content')['response'];

            $this->assertSame(200, $response1['statusCode']);
            $this->assertSame('Wrong response!', $response1['body']);

            $expectedResponse = 'Hello world from reloaded server worker via HTTP API!';
            $this->replaceContentInTestController($expectedResponse);
            $this->assertTestControllerResponseEquals($expectedResponse);

            $response2 = $serverClient->send('/test/replaced/content')['response'];

            $this->assertSame(200, $response2['statusCode']);
            $this->assertNotSame($expectedResponse, $response2['body']);

            $apiClient->reload();
            Coroutine::sleep(self::coverageEnabled() ? 3 : 1);

            $response3 = $serverClient->send('/test/replaced/content')['response'];

            $this->assertSame(200, $response3['statusCode']);
            $this->assertSame($expectedResponse, $response3['body']);
        });
    }

    public function testStartRequestApiToReloadCallStopUsingApiEnv(): void
    {
        static::bootKernel(['environment' => 'api']);
        $apiClient = static::getContainer()->get(ApiServerClientFactory::class)
            ->newClient()
        ;

        $envs = ['APP_ENV' => 'api'];
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        self::assertTrue($serverStart->isSuccessful());

        $this->runAsCoroutineAndWait(function () use ($apiClient, $envs): void {
            $this->deferServerStop([], $envs);
            $this->deferRestoreOriginalTemplateControllerResponse();

            $serverClient = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($serverClient->connect());

            $response1 = $serverClient->send('/test/replaced/content')['response'];

            $this->assertSame(200, $response1['statusCode']);
            $this->assertSame('Wrong response!', $response1['body']);

            $expectedResponse = 'Hello world from reloaded server worker via HTTP API!';
            $this->replaceContentInTestController($expectedResponse);
            $this->assertTestControllerResponseEquals($expectedResponse);

            $response2 = $serverClient->send('/test/replaced/content')['response'];

            $this->assertSame(200, $response2['statusCode']);
            $this->assertNotSame($expectedResponse, $response2['body']);

            $apiClient->reload();
            Coroutine::sleep(self::coverageEnabled() ? 3 : 1);

            $response3 = $serverClient->send('/test/replaced/content')['response'];

            $this->assertSame(200, $response3['statusCode']);
            $this->assertSame($expectedResponse, $response3['body']);
        });
    }

    private function replaceContentInTestController(string $text): void
    {
        file_put_contents(
            self::CONTROLLER_TEMPLATE_DEST,
            str_replace(self::CONTROLLER_TEMPLATE_REPLACE_TEXT, $text, file_get_contents(self::CONTROLLER_TEMPLATE_SRC))
        );
    }

    private function assertTestControllerResponseEquals(string $expected): void
    {
        self::assertSame(
            str_replace(self::CONTROLLER_TEMPLATE_REPLACE_TEXT, $expected, file_get_contents(self::CONTROLLER_TEMPLATE_SRC)),
            file_get_contents(self::CONTROLLER_TEMPLATE_DEST)
        );
    }

    private function deferRestoreOriginalTemplateControllerResponse(): void
    {
        defer(function (): void {
            $this->replaceContentInTestController(self::CONTROLLER_TEMPLATE_ORIGINAL_TEXT);
        });
    }
}
