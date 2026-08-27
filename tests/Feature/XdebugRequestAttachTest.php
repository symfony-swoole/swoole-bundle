<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Client\Http;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * EXPERIMENTAL feature - that an http request opens a debugging session, and only when it asks.
 *
 * Xdebug cannot make this decision here. It decides when the PHP script starts, and under swoole the
 * script is the worker process: a worker forks, boots, and serves for hours without PHP starting
 * again, so start_with_request and the XDEBUG_SESSION cookie it reads are evaluated once per worker at
 * fork time - when there is no request and no cookie. The bundle attaches from PHP instead, and what
 * is under test is when it chooses to.
 *
 * The attach itself is not exercised, and deliberately: a real one needs the extension loaded in the
 * container running the server and something listening on the debug port, neither of which a test
 * suite can assume. The environment puts a recording client behind the handler, which is the whole of
 * what the handler talks to.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Xdebug\AttachXdebugRequestHandler
 * @see docs/swoole-xdebug.md
 */
final class XdebugRequestAttachTest extends ServerTestCase
{
    private const string ENVIRONMENT = 'xdebug_requests';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testARequestAttachesOnlyWhenItCarriesATrigger(): void
    {
        $envs = ['APP_ENV' => self::ENVIRONMENT];
        $this->startServer($envs);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', self::port(), false);
            self::assertTrue($client->connect(self::connectTimeout(), waitIfNoConnection: true));

            $this->assertHelloWorldRequestSucceeded($client);

            self::assertSame(
                [],
                $this->recordedAttaches(),
                'A request with no trigger opened a debugging session. Every request would then pay a '
                . 'connect to a client that is usually not listening.',
            );

            // The cookie a browser debugging extension sets, and the one that used to pick between
            // two php containers in front of a server like this.
            $response = $client->send('/', Http::METHOD_GET, ['Cookie' => 'XDEBUG_SESSION=on'])['response'];
            self::assertSame(200, $response['statusCode']);

            $attaches = $this->recordedAttaches();

            self::assertCount(
                1,
                $attaches,
                'A request carrying the XDEBUG_SESSION cookie did not open a session, so nothing in '
                . 'it can be stepped through.',
            );

            // The worker stays attached, so a second triggering request through it must not attach
            // again - the part a per-request SAPI gets right for free and this cannot.
            $response = $client->send('/?XDEBUG_TRIGGER=1')['response'];
            self::assertSame(200, $response['statusCode']);

            self::assertSame(
                $attaches,
                $this->recordedAttaches(),
                'A worker that is already in a session attached a second time.',
            );
        });
    }

    /**
     * @param array<string, string> $envs
     */
    private function startServer(array $envs): void
    {
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], $envs);

        $serverStart->setTimeout(30);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);
    }

    /**
     * The pids that opened a session, one line each - written by the forked workers, read from here.
     *
     * @return list<string>
     */
    private function recordedAttaches(): array
    {
        $file = $this->getVarDirectoryPath() . '/log/xdebug-attaches.log';

        if (!is_file($file)) {
            return [];
        }

        $contents = file_get_contents($file);

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        return explode(PHP_EOL, trim($contents));
    }
}
