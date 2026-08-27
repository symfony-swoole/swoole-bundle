<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * EXPERIMENTAL feature - that a task worker opens a debugging session when a task reaches it.
 *
 * The counterpart to the request handler, for the half of the application no request runs. Where the
 * messenger task transport is in use a task is a message, so this is what makes a breakpoint in a
 * message handler reachable - and it is cheaper than attaching every worker at start, because an idle
 * task worker never connects at all.
 *
 * The environment routes RunDummy over swoole://task and leaves requests off, so the http worker that
 * accepts the dispatch does not attach. Exactly one session is therefore expected, and it must be
 * opened by a different process than the one that served the request - which is the whole point: the
 * handler runs in the task worker, and that is the process a breakpoint has to be in.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Xdebug\AttachXdebugTaskHandler
 * @see docs/swoole-xdebug.md
 */
final class XdebugTaskAttachTest extends ServerTestCase
{
    private const string ENVIRONMENT = 'xdebug_tasks';

    private const string DISPATCH_ROUTE = '/coroutines/message/run-dummy';

    private const int ATTACH_TIMEOUT_SECONDS = 15;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testATaskWorkerAttachesWhenATaskArrives(): void
    {
        $envs = ['APP_ENV' => self::ENVIRONMENT];

        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], $envs);

        $serverStart->setTimeout(30);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', self::port(), false);
            self::assertTrue($client->connect(self::connectTimeout(), waitIfNoConnection: true));

            // Nothing has been dispatched, so an idle task worker must not have connected to
            // anything - the cheapness of this attach point is half of why it exists.
            self::assertSame(
                [],
                $this->recordedAttaches(),
                'A session was opened before any task existed, so a task worker connects while idle.',
            );

            $response = $client->send(self::DISPATCH_ROUTE)['response'];
            self::assertSame(200, $response['statusCode']);

            $attaches = $this->awaitAttaches(1);

            self::assertCount(
                1,
                $attaches,
                'A task reached its worker without a session being opened, so a breakpoint in the '
                . 'message handler would never be hit.',
            );
        });
    }

    /**
     * @return list<string>
     */
    private function awaitAttaches(int $expected): array
    {
        $deadline = microtime(true) + self::ATTACH_TIMEOUT_SECONDS;

        do {
            $attaches = $this->recordedAttaches();

            if (count($attaches) >= $expected) {
                return $attaches;
            }

            usleep(100000);
        } while (microtime(true) < $deadline);

        return $this->recordedAttaches();
    }

    /**
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
