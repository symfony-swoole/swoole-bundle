<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ReplacedContentController;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleServerHMRTest extends ServerTestCase
{
    use ReplacedContentController;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkippedIfInotifyDisabled();
        $this->deleteVarDirectory();
        $this->writeOriginalTestController();
    }

    public function testStartCallHMRCallStopWithAutoRegistration(): void
    {
        $envs = ['APP_ENV' => 'auto'];
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);
            $this->deferRestoreOriginalTemplateControllerResponse();

            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(), waitIfNoConnection: true));

            $response1 = $client->send($this->replacedContentRoute())['response'];

            $this->assertSame(200, $response1['statusCode']);
            $this->assertSame('Wrong response!', $response1['body']);

            Coroutine::sleep(self::coverageEnabled() ? 5 : 3);

            $expectedResponse = 'Hello world from swoole reloaded worker by HMR!';
            $this->replaceContentInTestController($expectedResponse);
            $this->assertTestControllerResponseEquals($expectedResponse);

            Coroutine::sleep(self::coverageEnabled() ? 5 : 3);

            $response3 = $client->send($this->replacedContentRoute())['response'];

            $this->assertSame(200, $response3['statusCode']);
            $this->assertSame($expectedResponse, $response3['body']);
        });
    }

    public function testHMRDisabledByDefaultOnProduction(): void
    {
        $envs = ['APP_ENV' => 'prod'];
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);
            $this->deferRestoreOriginalTemplateControllerResponse();

            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(), waitIfNoConnection: true));

            $response1 = $client->send($this->replacedContentRoute())['response'];

            $this->assertSame(200, $response1['statusCode']);

            $expectedResponse = 'Wrong response!';
            $this->assertSame($expectedResponse, $response1['body']);

            Coroutine::sleep(self::coverageEnabled() ? 5 : 3);

            $this->replaceContentInTestController($expectedResponse);
            $this->assertTestControllerResponseEquals($expectedResponse);

            Coroutine::sleep(self::coverageEnabled() ? 5 : 3);

            $response3 = $client->send($this->replacedContentRoute())['response'];

            $this->assertSame(200, $response3['statusCode']);
            $this->assertSame($expectedResponse, $response3['body']);
        });
    }
}
