<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\WorkerContextLoggingCommand;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * What a log line says about where it came from, once platform.logging.worker_context is on.
 *
 * The fixture runs one http worker and one task worker holding two commands, which is the arrangement
 * the feature exists for: three processes writing one file, two of the writers being commands that share
 * a process and differ only in an argument.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Monolog\WorkerContextProcessor
 */
final class WorkerContextLoggingTest extends ServerTestCase
{
    private const string ENVIRONMENT = 'worker_context';

    private const string WEB_MARKER = 'from-the-web';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testEveryRecordSaysWhichWorkerCoroutineAndCommandWroteIt(): void
    {
        $log = $this->runServerAndReadItsLog();

        $webLine = $this->lineContaining($log, sprintf('Coroutine logging test: %s', self::WEB_MARKER));

        self::assertStringContainsString('"worker":"web-0"', $webLine);
        self::assertMatchesRegularExpression('/"cid":\d+/', $webLine);
        // An http request belongs to no command, and a field with nothing to say is left out rather
        // than written empty.
        self::assertStringNotContainsString('"command"', $webLine);

        $alphaLine = $this->lineContaining($log, sprintf('%s: alpha in the command.', $this->messagePrefix()));

        self::assertStringContainsString('"worker":"task-0"', $alphaLine);
        self::assertMatchesRegularExpression('/"cid":\d+/', $alphaLine);
        self::assertStringContainsString('"command":"test:worker-context:log alpha"', $alphaLine);
    }

    /**
     * The two commands share one task worker, so the process cannot tell them apart and the command
     * carried on the record is the only thing that can.
     */
    public function testCommandsSharingATaskWorkerAreToldApart(): void
    {
        $log = $this->runServerAndReadItsLog();

        $alpha = $this->lineContaining($log, sprintf('%s: alpha in the command.', $this->messagePrefix()));
        $beta = $this->lineContaining($log, sprintf('%s: beta in the command.', $this->messagePrefix()));

        self::assertStringContainsString('"worker":"task-0"', $alpha);
        self::assertStringContainsString('"worker":"task-0"', $beta);

        self::assertStringContainsString('"command":"test:worker-context:log alpha"', $alpha);
        self::assertStringContainsString('"command":"test:worker-context:log beta"', $beta);
    }

    /**
     * The case nearly all real work falls into: the line is written in a coroutine the command spawned
     * rather than in the one it runs in, so the command is only found by walking up the parent chain.
     */
    public function testWorkSpawnedByACommandIsStillAttributedToIt(): void
    {
        $log = $this->runServerAndReadItsLog();

        $spawned = $this->lineContaining(
            $log,
            sprintf('%s: alpha in a spawned coroutine.', $this->messagePrefix()),
        );
        $inTheCommand = $this->lineContaining(
            $log,
            sprintf('%s: alpha in the command.', $this->messagePrefix()),
        );

        self::assertStringContainsString('"worker":"task-0"', $spawned);
        self::assertStringContainsString('"command":"test:worker-context:log alpha"', $spawned);

        // A coroutine of its own, or the walk would not have been what answered.
        self::assertNotSame($this->cidOf($inTheCommand), $this->cidOf($spawned));
    }

    private function messagePrefix(): string
    {
        return WorkerContextLoggingCommand::MESSAGE_PREFIX;
    }

    /**
     * Started in the foreground and stopped by hand, as the other task worker tests do - and the log is
     * read only after the stop, so that everything the coroutine safe writer had queued is on disk.
     */
    private function runServerAndReadItsLog(): string
    {
        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], ['APP_ENV' => self::ENVIRONMENT]);

        $serverRun->setTimeout(30);
        $serverRun->start();

        $this->runAsCoroutineAndWait(static function (): void {
            $client = HttpClient::fromDomain('localhost', self::port(), false);
            self::assertTrue($client->connect(self::connectTimeout(), 1, true));

            $response = $client->send(sprintf('/log-warning/%s', self::WEB_MARKER))['response'];
            self::assertSame(200, $response['statusCode']);

            // Long enough for the task worker's commands to have started and logged, and for the
            // records handed to the write queue to have been written.
            Coroutine::sleep(2);
        });

        $serverRun->stop();

        return $this->readApplicationLog();
    }

    private function readApplicationLog(): string
    {
        $path = sprintf('%s/log/%s.log', $this->getVarDirectoryPath(), self::ENVIRONMENT);

        self::assertFileExists($path, 'Nothing was logged at all, the test would prove nothing.');

        return (string) file_get_contents($path);
    }

    private function lineContaining(string $log, string $needle): string
    {
        foreach (explode("\n", $log) as $line) {
            if (!str_contains($line, $needle)) {
                continue;
            }

            return $line;
        }

        self::fail(sprintf('No log record says "%s".', $needle));
    }

    private function cidOf(string $line): string
    {
        self::assertSame(1, preg_match('/"cid":(\d+)/', $line, $matches), 'The record carries no cid.');

        return $matches[1];
    }
}
