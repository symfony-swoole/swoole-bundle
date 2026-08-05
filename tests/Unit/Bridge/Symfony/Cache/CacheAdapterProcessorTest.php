<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\{
    ClassModificationProcessor,
    Proxifier,
};
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Cache\CacheAdapterProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\SimpleResetter;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Cache\Adapter\TraceableTagAwareAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(CacheAdapterProcessor::class)]
final class CacheAdapterProcessorTest extends TestCase
{
    private const string ADAPTER_RESETTER_ID = 'swoole_bundle.coroutines_support.cache_adapter_resetter';
    private const string TRACEABLE_RESETTER_ID = 'swoole_bundle.coroutines_support.cache_traceable_adapter_resetter';

    /**
     * The regression: the profiler's recording decorator is not an AbstractAdapter, so nothing pooled it,
     * and its per-request $calls was shared by every coroutine in the worker.
     */
    public function testTheRecordingDecoratorIsPooled(): void
    {
        $container = $this->containerWith(['cache.app' => TraceableAdapter::class]);

        $this->process($container);

        self::assertSame(
            [['resetter' => self::TRACEABLE_RESETTER_ID]],
            $container->getDefinition('cache.app')->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    /**
     * The one resetter that must not be used on it. TraceableAdapter::reset() resets the pool it wraps
     * before clearing its calls, and for the ArrayAdapter behind `cache.adapter.array` reset() is
     * clear() - so the obvious choice would empty every cache in the application on the way out of every
     * coroutine.
     */
    public function testTheRecordingDecoratorIsResetByClearingItsCallsAndNothingElse(): void
    {
        $container = $this->containerWith(['cache.app' => TraceableAdapter::class]);

        $this->process($container);

        $resetter = $container->getDefinition(self::TRACEABLE_RESETTER_ID);

        self::assertSame(SimpleResetter::class, $resetter->getClass());
        self::assertSame(['clearCalls'], $resetter->getArguments());
    }

    public function testTheTagAwareRecordingDecoratorIsTreatedTheSameWay(): void
    {
        $container = $this->containerWith(['cache.app.taggable' => TraceableTagAwareAdapter::class]);

        $this->process($container);

        self::assertSame(
            [['resetter' => self::TRACEABLE_RESETTER_ID]],
            $container->getDefinition('cache.app.taggable')->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    /**
     * Adapters keep the treatment they always had: reset() on one of those only commits what is still
     * deferred and forgets the id map, leaving the cached entries alone.
     */
    public function testRealAdaptersKeepTheirOwnResetter(): void
    {
        $container = $this->containerWith(['cache.adapter.filesystem' => FilesystemAdapter::class]);

        $this->process($container);

        self::assertSame(
            [['resetter' => self::ADAPTER_RESETTER_ID]],
            $container->getDefinition('cache.adapter.filesystem')->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
        self::assertSame(['reset'], $container->getDefinition(self::ADAPTER_RESETTER_ID)->getArguments());
    }

    /**
     * The pool a recording decorator wraps stays exactly as it was - shared, seen by every coroutine.
     * Pooling it would hand each coroutine a cache of its own and quietly halve the hit rate.
     */
    public function testTheWrappedPoolIsLeftShared(): void
    {
        $container = $this->containerWith([
            'cache.app' => TraceableAdapter::class,
            'cache.app.recorder_inner' => ArrayAdapter::class,
        ]);

        $this->process($container);

        $wrappedPool = $container->getDefinition('cache.app.recorder_inner');

        self::assertTrue($wrappedPool->isShared());
        self::assertSame([], $wrappedPool->getTag(ContainerConstants::TAG_STATEFUL_SERVICE));
    }

    /**
     * `cache.system` is built by a factory and declared as nothing more than the interface it satisfies,
     * so there was no class to build a proxy from and it stayed shared - one adapter written to by every
     * coroutine. Naming the class the factory is going to return is what lets it be pooled.
     */
    public function testTheSystemCacheGetsTheClassItsFactoryWillReturn(): void
    {
        $container = $this->containerWith([]);
        $container->register('cache.system', AdapterInterface::class)
            ->setFactory([AbstractAdapter::class, 'createSystemCache'])
            ->setArguments(['', 0, 'v1', '/tmp/pools']);

        $this->process($container);

        $definition = $container->getDefinition('cache.system');

        self::assertContains(
            $definition->getClass(),
            [PhpFilesAdapter::class, ChainAdapter::class],
            'The class has to be one of the two AbstractAdapter::createSystemCache() picks between.',
        );
        self::assertSame(
            [['resetter' => self::ADAPTER_RESETTER_ID]],
            $definition->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );

        // the factory keeps building it - only the class was filled in
        self::assertSame([AbstractAdapter::class, 'createSystemCache'], $definition->getFactory());
    }

    /**
     * Only that one factory is recognised. Any other service left as a bare interface is somebody else's
     * to explain, and guessing at it would be worse than leaving it alone.
     */
    public function testAnInterfaceDefinitionFromAnotherFactoryIsLeftAlone(): void
    {
        $container = $this->containerWith([]);
        $container->register('some.pool', AdapterInterface::class)
            ->setFactory(['App\CacheFactory', 'create']);

        $this->process($container);

        $definition = $container->getDefinition('some.pool');

        self::assertSame(AdapterInterface::class, $definition->getClass());
        self::assertSame([], $definition->getTag(ContainerConstants::TAG_STATEFUL_SERVICE));
    }

    public function testNoResetterIsRegisteredForAContainerWithoutCaches(): void
    {
        $container = $this->containerWith([]);

        $this->process($container);

        self::assertFalse($container->hasDefinition(self::TRACEABLE_RESETTER_ID));
        self::assertFalse($container->hasDefinition(self::ADAPTER_RESETTER_ID));
    }

    private function process(ContainerBuilder $container): void
    {
        (new CacheAdapterProcessor())->process(
            $container,
            new Proxifier($container, new ClassModificationProcessor($container)),
        );
    }

    /**
     * @param array<string, class-string> $services
     */
    private function containerWith(array $services): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());

        foreach ($services as $serviceId => $className) {
            $definition = $container->register($serviceId, $className);

            if ($className !== TraceableAdapter::class && $className !== TraceableTagAwareAdapter::class) {
                continue;
            }

            $definition->setArguments([new Reference($serviceId . '.recorder_inner')]);
        }

        return $container;
    }
}
