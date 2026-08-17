<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ReplacedContentController;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleServerReloadCommandTest extends ServerTestCase
{
    use ReplacedContentController;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
        $this->writeOriginalTestController();
    }

    public function testStartCallReloadCallStop(): void
    {
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ]);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function (): void {
            $this->deferServerStop();
            $this->deferRestoreOriginalTemplateControllerResponse();

            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(), waitIfNoConnection: true));

            $response1 = $client->send($this->replacedContentRoute())['response'];

            $this->assertSame(200, $response1['statusCode']);
            $this->assertSame('Wrong response!', $response1['body']);

            $expectedResponse = 'Hello world from reloaded server worker!';
            $this->replaceContentInTestController($expectedResponse);
            $this->assertTestControllerResponseEquals($expectedResponse);

            $response2 = $client->send($this->replacedContentRoute())['response'];

            $this->assertSame(200, $response2['statusCode']);
            $this->assertNotSame($expectedResponse, $response2['body']);

            $this->runSwooleServerReload();
            Coroutine::sleep(self::coverageEnabled() ? 3 : 1);

            $response3 = $client->send($this->replacedContentRoute())['response'];

            $this->assertSame(200, $response3['statusCode']);
            $this->assertSame($expectedResponse, $response3['body']);
        });
    }

    private function runSwooleServerReload(): void
    {
        $serverReload = $this->createConsoleProcess(['swoole:server:reload']);

        $serverReload->setTimeout(3);
        $serverReload->run();

        $this->assertProcessSucceeded($serverReload);

        if (self::coverageEnabled()) {
            return;
        }

        self::assertStringContainsString(
            'Swoole HTTP Server\'s workers reloaded successfully',
            $serverReload->getOutput()
        );
    }
}
