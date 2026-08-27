<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * EXPERIMENTAL feature - that every worker opens a debugging session as it starts, task workers too.
 *
 * This is the attach point that reaches what no request can: message handlers, projections, the long
 * running commands a task worker hosts, and the kernel boot itself. It is also the one xdebug appears
 * to offer and cannot deliver - xdebug.start_with_request=yes attaches the *master*, before it has
 * forked anything, and with a client listening the master is held there and the server never finishes
 * starting. Doing it from onWorkerStart is what puts the session in the right process at the right
 * moment, and puts one in every replacement worker after a reload or a recycle.
 *
 * The counts are pinned by the environment: two http workers and one task worker, so three processes
 * run onWorkerStart and neither the master nor the manager does.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Xdebug\AttachXdebugWorkerStartHandler
 * @see docs/swoole-xdebug.md
 */
final class XdebugWorkerStartAttachTest extends ServerTestCase
{
    private const string ENVIRONMENT = 'xdebug_workers';

    private const int HTTP_WORKERS = 2;

    private const int TASK_WORKERS = 1;

    private const int ATTACH_TIMEOUT_SECONDS = 15;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testEveryWorkerAttachesAsItStartsWithoutAnyRequest(): void
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

            $expected = self::HTTP_WORKERS + self::TASK_WORKERS;
            $attaches = $this->awaitAttaches($expected);

            // No request has been sent at this point, and the environment has requests off anyway, so
            // every one of these came from a worker starting.
            self::assertCount(
                $expected,
                $attaches,
                sprintf(
                    'Expected one session per worker - %d http and %d task - and got %d. A task '
                    . 'worker missing here is the half of the application no request reaches.',
                    self::HTTP_WORKERS,
                    self::TASK_WORKERS,
                    count($attaches),
                ),
            );

            self::assertCount(
                $expected,
                array_unique($attaches),
                'Two sessions were opened by the same process, so a worker attached twice rather '
                . 'than each worker attaching once.',
            );

            // Still serving. Worth asserting because the failure this whole feature exists to avoid
            // is a session that stops the process holding it: a worker that attached and never got
            // on with its work is indistinguishable from one that is merely slow, until a request
            // times out.
            $client = HttpClient::fromDomain('localhost', self::port(), false);
            self::assertTrue($client->connect(self::connectTimeout(), waitIfNoConnection: true));
            $this->assertHelloWorldRequestSucceeded($client);
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
