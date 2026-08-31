<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Monolog;

use DateTimeImmutable;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine\Scheduler;
use SwooleBundle\SwooleBundle\Bridge\Monolog\RotatingFileHandler;
use SwooleBundle\SwooleBundle\Bridge\Monolog\StreamHandler;
use SwooleBundle\SwooleBundle\Bridge\OpenSwoole\OpenSwoole;
use SwooleBundle\SwooleBundle\Bridge\Swoole\Swoole as SwooleAdapter;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Common\System\Extension;

final class CoroutineSafeWritesTest extends TestCase
{
    private const int LOGGING_COROUTINES = 50;

    private const int RECORDS_PER_COROUTINE = 10;

    /**
     * Long enough for a single write to have a chance of being split up when it is not serialized.
     */
    private const int MESSAGE_LENGTH = 8192;

    private string $logDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->logDir = sprintf('%s/swoole-bundle-monolog-%s', sys_get_temp_dir(), bin2hex(random_bytes(6)));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach (glob(sprintf('%s/*', $this->logDir)) ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->logDir)) {
            rmdir($this->logDir);
        }

        $this->logDir = '';
    }

    /**
     * The directory does not exist yet either, so the lazy directory creation and the check-then-open of
     * the stream are exercised by all coroutines at once.
     */
    public function testConcurrentlyWrittenRecordsStayIntactAndOrdered(): void
    {
        $swoole = $this->swoole();
        $logFile = sprintf('%s/concurrent.log', $this->logDir);

        $handler = new StreamHandler($logFile, Level::Debug);
        $handler->setSwoole($swoole);
        $handler->setFormatter(new LineFormatter("%message%\n"));

        $swoole->enableCoroutines();

        try {
            $scheduler = new Scheduler();

            for ($coroutine = 0; $coroutine < self::LOGGING_COROUTINES; $coroutine++) {
                $scheduler->add(static function () use ($handler, $coroutine): void {
                    for ($record = 0; $record < self::RECORDS_PER_COROUTINE; $record++) {
                        $handler->handle(self::newRecord(self::message($coroutine, $record)));
                    }
                });
            }

            $scheduler->start();
        } finally {
            $swoole->disableCoroutines();
        }

        $handler->close();

        $lines = $this->readLines($logFile);

        self::assertCount(self::LOGGING_COROUTINES * self::RECORDS_PER_COROUTINE, $lines);

        // no line may be torn apart by another one, and every coroutine keeps its own order
        foreach ($lines as $line) {
            self::assertSame(self::MESSAGE_LENGTH, strlen($line));
        }

        for ($coroutine = 0; $coroutine < self::LOGGING_COROUTINES; $coroutine++) {
            $expected = [];

            for ($record = 0; $record < self::RECORDS_PER_COROUTINE; $record++) {
                $expected[] = self::message($coroutine, $record);
            }

            $written = array_values(array_filter(
                $lines,
                static fn(string $line): bool => str_starts_with($line, sprintf('%d/', $coroutine)),
            ));

            self::assertSame($expected, $written, sprintf('Coroutine %d lost or reordered records.', $coroutine));
        }
    }

    /**
     * Closing has to write out whatever is still queued before the stream goes away.
     */
    public function testClosingFromAnotherCoroutineWritesOutEverythingQueued(): void
    {
        $swoole = $this->swoole();
        $logFile = sprintf('%s/drained.log', $this->logDir);

        $handler = new StreamHandler($logFile, Level::Debug);
        $handler->setSwoole($swoole);
        $handler->setFormatter(new LineFormatter("%message%\n"));

        $swoole->enableCoroutines();

        try {
            $scheduler = new Scheduler();

            $scheduler->add(static function () use ($handler): void {
                for ($record = 0; $record < self::RECORDS_PER_COROUTINE; $record++) {
                    $handler->handle(self::newRecord(self::message(0, $record)));
                }
            });

            $scheduler->add(static function () use ($handler, $logFile): void {
                $handler->close();

                self::assertCount(
                    self::RECORDS_PER_COROUTINE,
                    file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [],
                );
            });

            $scheduler->start();
        } finally {
            $swoole->disableCoroutines();
        }
    }

    public function testWritingOutsideOfACoroutineStillWorks(): void
    {
        $logFile = sprintf('%s/no-coroutine.log', $this->logDir);

        $handler = new StreamHandler($logFile, Level::Debug);
        $handler->setSwoole($this->swoole());
        $handler->setFormatter(new LineFormatter("%message%\n"));

        $handler->handle(self::newRecord('written without a scheduler'));
        $handler->close();

        self::assertSame(['written without a scheduler'], $this->readLines($logFile));
    }

    /**
     * The counterpart to the case below: what is stripped on the way into the queue must be nothing the
     * log was going to say.
     *
     * It is not, because Monolog formats before it writes - `AbstractProcessingHandler::handle()` runs the
     * processors, sets `$record->formatted` and only then calls `write()`. The context, the extra and
     * whatever a processor added are already rendered into that string by the time the queue sees it, and
     * a stream handler reads nothing else. The default line format is used here on purpose: it is the one
     * that renders both, so a record that lost them would show it.
     */
    public function testTheWrittenLineStillCarriesTheContextAndTheExtra(): void
    {
        $swoole = $this->swoole();
        $logFile = sprintf('%s/rendered.log', $this->logDir);

        $handler = new StreamHandler($logFile, Level::Debug);
        $handler->setSwoole($swoole);
        $handler->setFormatter(new LineFormatter());

        $swoole->enableCoroutines();

        try {
            $scheduler = new Scheduler();
            $scheduler->add(static function () use ($handler): void {
                $handler->handle(new LogRecord(
                    new DateTimeImmutable(),
                    'test',
                    Level::Warning,
                    'something went wrong',
                    ['order' => 'ord-42'],
                    ['worker' => 'task-1'],
                ));
            });
            $scheduler->start();
        } finally {
            $swoole->disableCoroutines();
        }

        $handler->close();

        $line = $this->readLines($logFile)[0] ?? '';

        self::assertStringContainsString('something went wrong', $line);
        self::assertStringContainsString('ord-42', $line, 'The context was dropped instead of written.');
        self::assertStringContainsString('task-1', $line, 'The extra was dropped instead of written.');
        self::assertStringContainsString('test.WARNING', $line);
    }

    /**
     * A record is released where it was logged, never where it was written.
     *
     * The consumer holds a payload until it pops the next one, so anything a record still references is
     * destructed in the consumer's coroutine - and a record carries more than it looks. An exception in
     * the context brings its trace, and PHP keeps every frame's arguments, so one failed HTTP call puts a
     * live response in there whose destructor tears down its buffer stream and its connection state. With
     * fiber context checking on, that is the worker gone rather than a warning.
     *
     * The tripwire stands in for all of it: nothing but the formatted line may reach the consumer.
     */
    public function testWhatARecordCarriesIsReleasedInTheCoroutineThatLoggedIt(): void
    {
        $swoole = $this->swoole();
        $logFile = sprintf('%s/context.log', $this->logDir);

        $handler = new StreamHandler($logFile, Level::Debug);
        $handler->setSwoole($swoole);
        $handler->setFormatter(new LineFormatter("%message%\n"));

        $site = new DestructionSite();

        $swoole->enableCoroutines();

        try {
            $scheduler = new Scheduler();
            $scheduler->add(static function () use ($handler, $site): void {
                $site->noteTheCoroutineLoggingHere();

                $handler->handle(new LogRecord(
                    new DateTimeImmutable(),
                    'test',
                    Level::Info,
                    'carrying something with a destructor',
                    ['tripwire' => new DestructionTripwire($site)],
                ));
            });
            $scheduler->start();
        } finally {
            $swoole->disableCoroutines();
        }

        $handler->close();

        self::assertSame(['carrying something with a destructor'], $this->readLines($logFile));
        self::assertSame(
            $site->loggedIn(),
            $site->destroyedIn(),
            'The context outlived the coroutine that logged it and was released by the write queue.',
        );
    }

    /**
     * The rotating handler closes itself from within its own write() to trigger a rotation, which makes it
     * reenter the queue it is being consumed by.
     */
    public function testTheRotatingHandlerWritesEveryRecordDespiteRotatingFromWithinItsWrite(): void
    {
        $swoole = $this->swoole();
        $handler = new RotatingFileHandler(sprintf('%s/rotating.log', $this->logDir), 5, Level::Debug);
        $handler->setSwoole($swoole);
        $handler->setFormatter(new LineFormatter("%message%\n"));

        $swoole->enableCoroutines();

        try {
            $scheduler = new Scheduler();

            for ($coroutine = 0; $coroutine < self::LOGGING_COROUTINES; $coroutine++) {
                $scheduler->add(static function () use ($handler, $coroutine): void {
                    for ($record = 0; $record < self::RECORDS_PER_COROUTINE; $record++) {
                        $handler->handle(self::newRecord(self::message($coroutine, $record)));
                    }
                });
            }

            $scheduler->start();
        } finally {
            $swoole->disableCoroutines();
        }

        $handler->close();

        $lines = [];

        foreach (glob(sprintf('%s/*', $this->logDir)) ?: [] as $file) {
            $lines = [...$lines, ...$this->readLines($file)];
        }

        self::assertCount(self::LOGGING_COROUTINES * self::RECORDS_PER_COROUTINE, $lines);
    }

    private static function message(int $coroutine, int $record): string
    {
        $prefix = sprintf('%d/%d/', $coroutine, $record);

        return $prefix . str_repeat('x', self::MESSAGE_LENGTH - strlen($prefix));
    }

    private static function newRecord(string $message): LogRecord
    {
        return new LogRecord(new DateTimeImmutable(), 'test', Level::Info, $message);
    }

    /**
     * @return list<string>
     */
    private function readLines(string $logFile): array
    {
        self::assertFileExists($logFile);

        return file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    }

    private function swoole(): Swoole
    {
        if (extension_loaded(Extension::SWOOLE)) {
            return new SwooleAdapter();
        }

        if (extension_loaded(Extension::OPENSWOOLE)) {
            return new OpenSwoole();
        }

        self::markTestSkipped('No supported extension loaded.');
    }
}
