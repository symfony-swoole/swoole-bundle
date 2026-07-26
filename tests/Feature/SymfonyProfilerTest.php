<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SymfonyProfilerTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    #[DataProvider('environments')]
    public function testProfilerIsEnabled(string $environment): void
    {
        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            '--port=9999',
        ], ['APP_ENV' => $environment]);

        $serverRun->setTimeout(10);
        $serverRun->start();

        $this->runAsCoroutineAndWait(function (): void {
            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect(3, 1, true));

            $response = $client->send('/twig')['response'];

            $this->assertSame(200, $response['statusCode']);
            $this->assertArrayHasKey('headers', $response);
            $this->assertIsArray($response['headers']);
            $this->assertArrayHasKey('x-debug-token', $response['headers']);
            $this->assertNotEmpty($response['headers']['x-debug-token']);
            $debugToken = $response['headers']['x-debug-token'];

            // Since Symfony 7.4 it looks like the profile is being written later than the response is sent
            // Although this only goes for 7.4.0, so the sleep call may be removed later
            usleep(200000); // 200 ms
            $profileToolbarResponse = $client->send('/_wdt/' . $debugToken)['response'];

            $this->assertSame(200, $profileToolbarResponse['statusCode']);
            $this->assertArrayHasKey('body', $profileToolbarResponse);
            $this->assertIsString($profileToolbarResponse['body']);

            $this->assertMatchesRegularExpression(
                '/(<div id="sfMiniToolbar-[^"]+" class="sf-minitoolbar")|'
                . '(<div id="sfToolbarClearer-[^"]+" class="sf-toolbar-clearer")/',
                $profileToolbarResponse['body']
            );

            $profilerResponse = $client->send('/_profiler/' . $debugToken)['response'];
            $this->assertSame(200, $profilerResponse['statusCode']);
        });

        $serverRun->stop();
    }

    /**
     * @return array<array<string>>
     */
    public static function environments(): array
    {
        return [
            ['profiler'],
            ['coroutines_profiler'],
        ];
    }
}
