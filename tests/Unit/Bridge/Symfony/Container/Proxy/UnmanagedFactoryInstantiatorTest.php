<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Container\Proxy;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ProxyManager\Configuration;
use stdClass;
use SwooleBundle\SwooleBundle\Bridge\CommonSwoole\SystemSwooleFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generator;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Instantiator;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\UnmanagedFactoryInstantiator;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use SwooleBundle\SwooleBundle\Component\Locking\Channel\ChannelMutexFactory;
use SwooleBundle\SwooleBundle\Component\Locking\FirstTimeOnly\FirstTimeOnlyMutexFactory;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;
use SwooleBundle\SwooleBundle\Component\Locking\MutexFactory;
use SwooleBundle\SwooleBundle\Component\Locking\RecursiveOwner\RecursiveOwnerMutexFactory;
use SwooleBundle\SwooleBundle\Coroutine\CoroutinePool;

/**
 * That two coroutines wrapping an unmanaged factory do not generate its proxy class at the same time.
 *
 * Generating one is once-per-class work that both proxy factories involved memoize on themselves -
 * ProxyManager on AbstractBaseFactory::$checkedClasses, this bundle's Generator on its own - and it is
 * file I/O, so the coroutine doing it is suspended in the middle of doing it. Two coroutines arriving
 * together therefore both generate, and both write the memo.
 *
 * The container has always guarded the equivalent: BlockingContainer serialises the first instantiation
 * of any service asked of it. What it cannot guard is a service first reached some other way, and
 * Symfony's ServiceLocator - which resolves every messenger handler and every controller subscriber -
 * implements get() itself without ever entering Container::get(). That is the door this came through:
 * two messenger consumers sending mail at once, each reaching a pooled mail transport for the first
 * time.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Container\BlockingContainer
 */
#[CoversClass(UnmanagedFactoryInstantiator::class)]
final class UnmanagedFactoryInstantiatorTest extends TestCase
{
    private const int INSTANCES_LIMIT = 10;

    public function testTwoCoroutinesDoNotGenerateAProxyClassAtTheSameTime(): void
    {
        $proxyFactory = new RecordingProxyFactory();

        $this->wrapConcurrently($proxyFactory, $this->instantiationLocking());

        self::assertSame(
            ['enter', 'leave', 'enter', 'leave'],
            $proxyFactory->events(),
            'One coroutine was generating a proxy class while another was already inside doing the '
            . 'same, which is how both come to write the memo that says it has been done.',
        );
    }

    /**
     * The control, and the reason the assertion above means anything: with nothing holding the door,
     * the same two coroutines do overlap. Without this a lock that was never acquired would pass, as
     * would a test whose coroutines simply ran one after the other.
     */
    public function testTheGenerationsOverlapWhenNothingSerialisesThem(): void
    {
        $proxyFactory = new RecordingProxyFactory();

        $this->wrapConcurrently($proxyFactory, $this->openGate());

        self::assertSame(
            ['enter', 'enter', 'leave', 'leave'],
            $proxyFactory->events(),
            'The coroutines did not overlap even unlocked, so the test above proves nothing about the '
            . 'lock.',
        );
    }

    /**
     * Two coroutines wrapping a factory of the same class, which is the pair that would generate one
     * proxy class twice.
     */
    private function wrapConcurrently(RecordingProxyFactory $proxyFactory, MutexFactory $locking): void
    {
        $instantiator = $this->newInstantiator($proxyFactory, $locking);
        $wrap = static fn(): object => $instantiator->newInstance(
            new StubUnmanagedFactory(),
            [['factoryMethod' => 'create', 'returnType' => stdClass::class]],
            self::INSTANCES_LIMIT,
        );

        CoroutinePool::fromCoroutines($wrap, $wrap)->run();
    }

    private function newInstantiator(
        RecordingProxyFactory $proxyFactory,
        MutexFactory $instantiationLocking,
    ): UnmanagedFactoryInstantiator {
        $channels = new ChannelMutexFactory();

        return new UnmanagedFactoryInstantiator(
            $proxyFactory,
            new Instantiator(new Generator(new Configuration())),
            new ServicePoolContainer([]),
            $channels,
            new FirstTimeOnlyMutexFactory($channels),
            SystemSwooleFactory::newFactoryInstance()->newInstance(),
            $instantiationLocking,
        );
    }

    private function instantiationLocking(): MutexFactory
    {
        return new RecursiveOwnerMutexFactory(
            SystemSwooleFactory::newFactoryInstance()->newInstance(),
            new ChannelMutexFactory(),
        );
    }

    /**
     * A mutex that locks nothing, standing in for the way this behaved before there was one.
     */
    private function openGate(): MutexFactory
    {
        return new class implements MutexFactory {
            #[Override]
            public function newMutex(): Mutex
            {
                return new class implements Mutex {
                    #[Override]
                    public function acquire(): void {}

                    #[Override]
                    public function release(): void {}

                    #[Override]
                    public function isAcquired(): bool
                    {
                        return false;
                    }
                };
            }
        };
    }
}
