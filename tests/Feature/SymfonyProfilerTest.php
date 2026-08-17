<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SymfonyProfilerTest extends ServerTestCase
{
    /**
     * Generous on purpose: both commands are awaited synchronously and return as soon as they are done,
     * so this only ever caps a machine that has genuinely stalled.
     */
    private const int PROCESS_TIMEOUT_SECONDS = 60;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    #[DataProvider('environments')]
    public function testProfilerIsEnabled(string $environment): void
    {
        $envs = ['APP_ENV' => $environment];

        // setUp() throws the compiled cache away before every test, so without this the server compiles
        // the container while booting. For the profiler environments that is slow enough - especially
        // under xdebug in the coverage builds - to matter. Compiling it up front keeps the boot short.
        $clearCache = $this->createConsoleProcess(['cache:clear'], $envs);
        $clearCache->setTimeout(self::PROCESS_TIMEOUT_SECONDS);
        $clearCache->disableOutput();
        $clearCache->run();

        $this->assertProcessSucceeded($clearCache);

        // swoole:server:start returns only once the server is actually listening, unlike
        // swoole:server:run which stays in the foreground and leaves the client racing the boot - a race
        // the client loses on a slow machine, where booting takes longer than the few seconds it waits.
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], $envs);

        $serverStart->setTimeout(self::PROCESS_TIMEOUT_SECONDS);
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(), 1, true));

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

            // The name of the template the request rendered, which the toolbar takes from the profiling
            // tree the collector stored at the end of that request and read back for this one:
            //
            //     {% set template = collector.templates|keys|first %}
            //
            // Nothing else on the page knows it. Under coroutines the collector is handed a pooled
            // profile, and storing what a proxy serializes to leaves nothing readable at this end - a
            // tree that comes back empty prints no entry view at all, while one that comes back under a
            // class the collector refuses to load fails the whole toolbar. Asserting the toolbar merely
            // renders catches only the second.
            $this->assertStringContainsString('base.html.twig', $profileToolbarResponse['body']);

            $profilerResponse = $client->send('/_profiler/' . $debugToken)['response'];
            $this->assertSame(200, $profilerResponse['statusCode']);
        });
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
