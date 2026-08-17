<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Scheduler;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;
use stdClass;
use Swoole\Http\Server;
use Swoole\Timer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\CoWrapper;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePool;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolEntry;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Scheduler\Scheduler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Scheduler\WithScheduler;
use SwooleBundle\SwooleBundle\Coroutine\CoroutinePool;
use Symfony\Component\Cache\LockRegistry;

#[Group('unit')]
final class WithSchedulerTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $originalLockRegistryFiles;

    protected function setUp(): void
    {
        $this->originalLockRegistryFiles = self::lockRegistryFiles();
    }

    protected function tearDown(): void
    {
        LockRegistry::setFiles($this->originalLockRegistryFiles);
    }

    public function testRegisterSwooleTick(): void
    {
        self::assertEmpty(iterator_to_array(Timer::list()));
        ($withScheduler = new WithScheduler(
            self::createStub(Scheduler::class),
            self::emptyCoWrapper(),
            self::createStub(LoggerInterface::class),
        ))->configure(self::createStub(Server::class));

        $ticks = iterator_to_array(Timer::list());

        self::assertNotEmpty($ticks);

        $withScheduler->__destruct();

        self::assertEmpty(iterator_to_array(Timer::list()));
    }

    public function testConfigureDisablesLockRegistryFileLocking(): void
    {
        LockRegistry::setFiles(['some/file.php']);

        ($withScheduler = new WithScheduler(
            self::createStub(Scheduler::class),
            self::emptyCoWrapper(),
            self::createStub(LoggerInterface::class),
        ))->configure(self::createStub(Server::class));

        self::assertSame([], self::lockRegistryFiles());

        $withScheduler->__destruct();
    }

    public function testTickRunsSchedulerAndCallsAfterTickHook(): void
    {
        $scheduler = $this->createMock(Scheduler::class);
        $scheduler->expects($this->once())
            ->method('run');

        $captured = new stdClass();
        $captured->afterTickCalls = 0;

        $withScheduler = new WithScheduler(
            $scheduler,
            self::emptyCoWrapper(),
            self::createStub(LoggerInterface::class),
            afterTick: static function () use ($captured): void {
                $captured->afterTickCalls += 1;
            },
        );

        CoroutinePool::fromCoroutines(static function () use ($withScheduler): void {
            $withScheduler->tick();
        })->run();

        self::assertSame(1, $captured->afterTickCalls);
    }

    public function testTickReleasesPooledServicesForTheCurrentCoroutine(): void
    {
        // Real CoWrapper/ServicePoolContainer instead of mocks: both are final, and this is
        // exactly the wiring the request- and message-boundary handlers rely on for every HTTP
        // request/async message, so it's worth verifying tick() plugs into the real thing rather
        // than just a stub.
        $pool = $this->createMock(ServicePool::class);
        $pool->expects($this->once())
            ->method('releaseFromCoroutine');

        $coWrapper = new CoWrapper(new ServicePoolContainer([new ServicePoolEntry($pool)]));

        $withScheduler = new WithScheduler(
            self::createStub(Scheduler::class),
            $coWrapper,
            self::createStub(LoggerInterface::class),
        );

        CoroutinePool::fromCoroutines(static function () use ($withScheduler): void {
            $withScheduler->tick();
        })->run();
    }

    public function testTickSkipsWhileAlreadyRunning(): void
    {
        $scheduler = $this->createMock(Scheduler::class);

        $captured = new stdClass();
        $captured->afterTickCalls = 0;

        $withScheduler = new WithScheduler(
            $scheduler,
            self::emptyCoWrapper(),
            self::createStub(LoggerInterface::class),
            afterTick: static function () use ($captured): void {
                $captured->afterTickCalls += 1;
            },
        );

        $scheduler->expects($this->once())
            ->method('run')
            ->willReturnCallback(static function () use ($withScheduler): void {
                // Simulates an overlapping tick firing while this one is still in-flight.
                $withScheduler->tick();
            });

        CoroutinePool::fromCoroutines(static function () use ($withScheduler): void {
            $withScheduler->tick();
        })->run();

        self::assertSame(1, $captured->afterTickCalls);
    }

    public function testTickResetsRunningFlagAfterExceptionSoLaterTicksAreNotSkipped(): void
    {
        $scheduler = $this->createMock(Scheduler::class);
        $scheduler->expects($this->exactly(2))
            ->method('run')
            ->willReturnCallback(static function (): void {
                static $calls = 0;
                ++$calls;

                if ($calls === 1) {
                    throw new RuntimeException('boom');
                }
            });

        $withScheduler = new WithScheduler(
            $scheduler,
            self::emptyCoWrapper(),
            self::createStub(LoggerInterface::class),
        );

        // A failed tick must not propagate - Timer::tick has no caller to catch it, so an
        // uncaught exception here crashes the entire process.
        CoroutinePool::fromCoroutines(static function () use ($withScheduler): void {
            $withScheduler->tick();
        })->run();

        // A stuck/failed tick must not permanently block future ticks from running.
        CoroutinePool::fromCoroutines(static function () use ($withScheduler): void {
            $withScheduler->tick();
        })->run();
    }

    public function testTickLogsAndSwallowsExceptionsFromScheduler(): void
    {
        $scheduler = $this->createMock(Scheduler::class);
        $exception = new RuntimeException('boom');
        $scheduler->expects($this->once())
            ->method('run')
            ->willThrowException($exception);

        // Asserting via ->with() on a mock invoked from inside a coroutine crashes PHPUnit's
        // parameter-matcher (it walks the call stack for the enclosing TestCase, which a
        // coroutine's separate stack doesn't have) - capture the call instead and assert on it
        // from the outer, non-coroutine stack once the coroutine finishes.
        $captured = new stdClass();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->willReturnCallback(static function (string $message, array $context) use ($captured): void {
                $captured->message = $message;
                $captured->context = $context;
            });

        $withScheduler = new WithScheduler(
            $scheduler,
            self::emptyCoWrapper(),
            $logger,
        );

        CoroutinePool::fromCoroutines(static function () use ($withScheduler): void {
            $withScheduler->tick();
        })->run();

        self::assertSame('Scheduler tick failed', $captured->message ?? null);
        self::assertSame([
            'exception' => $exception,
        ], $captured->context ?? null);
    }

    public function testTickSkipsPooledServiceResetAndLogsAWarningOutsideACoroutine(): void
    {
        // Reproduces the actual failure mode directly, without needing swoole's own reload/
        // recycle timing: calling tick() with no enclosing coroutine is exactly the condition
        // (Coroutine::getCid() === -1) that CoWrapper::defer() can't handle - this must not call
        // defer() at all in that case, and must not crash this test process doing it.
        $pool = $this->createMock(ServicePool::class);
        $pool->expects($this->never())
            ->method('releaseFromCoroutine');

        $coWrapper = new CoWrapper(new ServicePoolContainer([new ServicePoolEntry($pool)]));

        $scheduler = $this->createMock(Scheduler::class);
        $scheduler->expects($this->once())
            ->method('run');

        $captured = new stdClass();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->willReturnCallback(static function (string $message) use ($captured): void {
                $captured->message = $message;
            });

        $withScheduler = new WithScheduler($scheduler, $coWrapper, $logger);

        // No coroutine wrapper - this is the whole point of the test.
        $withScheduler->tick();

        self::assertNotNull($captured->message ?? null);
        self::assertStringContainsString('no coroutine context', $captured->message);
    }

    private static function emptyCoWrapper(): CoWrapper
    {
        return new CoWrapper(new ServicePoolContainer([]));
    }

    /**
     * @return list<string>
     */
    private static function lockRegistryFiles(): array
    {
        $property = new ReflectionClass(LockRegistry::class)->getProperty('files');

        /** @var list<string> $files */
        $files = $property->getValue();

        return $files;
    }
}
