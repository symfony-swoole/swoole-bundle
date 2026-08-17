<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Cache;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\ServiceProxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\SimpleResetter;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Throwable;

final class CacheAdapterProcessor implements CompileProcessor
{
    private const string ADAPTER_RESETTER_ID = 'swoole_bundle.coroutines_support.cache_adapter_resetter';
    private const string TRACEABLE_RESETTER_ID = 'swoole_bundle.coroutines_support.cache_traceable_adapter_resetter';

    public function process(ContainerBuilder $container, ServiceProxifier $proxifier): void
    {
        $taggedAdapters = 0;
        $taggedTraceables = 0;

        foreach ($container->getDefinitions() as $definition) {
            try {
                if ($definition->isAbstract()) {
                    continue;
                }

                /** @var class-string $className */
                $className = $this->nameTheSystemCacheClass($definition) ?? $definition->getClass();

                if ($this->isTraceableAdapter($className)) {
                    $definition->addTag(
                        ContainerConstants::TAG_STATEFUL_SERVICE,
                        ['resetter' => self::TRACEABLE_RESETTER_ID],
                    );
                    $taggedTraceables++;

                    continue;
                }

                if (is_subclass_of($className, AbstractAdapter::class) || $className === ChainAdapter::class) {
                    foreach ($definition->getArguments() as $argument) {
                        if ($argument instanceof AbstractArgument) {
                            continue 2;
                        }
                    }

                    $definition->addTag(
                        ContainerConstants::TAG_STATEFUL_SERVICE,
                        ['resetter' => self::ADAPTER_RESETTER_ID],
                    );
                    $taggedAdapters++;
                }
            } catch (Throwable) {
                // ignore
            }
        }

        if ($taggedAdapters > 0) {
            // AbstractAdapter::reset() only commits what is still deferred and forgets the id map - it
            // leaves what is cached alone
            $container->setDefinition(self::ADAPTER_RESETTER_ID, $this->newResetter('reset'));
        }

        if ($taggedTraceables === 0) {
            return;
        }

        $container->setDefinition(self::TRACEABLE_RESETTER_ID, $this->newResetter('clearCalls'));
    }

    /**
     * Writes the class of the system cache onto its definition, and says which one it is.
     *
     * `cache.system` - and everything parented on it, so cache.validator, cache.serializer,
     * cache.property_info - is built by AbstractAdapter::createSystemCache() and declared as nothing more
     * than the interface it satisfies. A service whose class is unknown cannot be pooled, since there is
     * nothing to generate a proxy from, so it stayed shared: one PhpFilesAdapter written to by every
     * coroutine in the worker.
     *
     * The factory decides between the two on facts that hold as much while the container is compiled as
     * they do while it runs - both happen in the same PHP build, and a Swoole worker is CLI just as the
     * compile step is - so asking the same questions here names the class the factory is going to return.
     * The factory itself is left in place; only the class is filled in.
     *
     * What that buys is coroutine safety for free: everything the adapter keeps to itself is bookkeeping
     * ($deferred, $namespaceVersion, $ids), while the cached entries live in files, so an instance per
     * coroutine costs nothing and shares everything that matters.
     */
    private function nameTheSystemCacheClass(Definition $definition): ?string
    {
        if ($definition->getClass() !== AdapterInterface::class) {
            return null;
        }

        $factory = $definition->getFactory();

        if (!is_array($factory) || count($factory) !== 2) {
            return null;
        }

        [$factoryClass, $factoryMethod] = $factory;

        if ((string) $factoryClass !== AbstractAdapter::class || $factoryMethod !== 'createSystemCache') {
            return null;
        }

        $className = $this->systemCacheClass();
        $definition->setClass($className);

        return $className;
    }

    /**
     * The same choice AbstractAdapter::createSystemCache() makes: the opcache-backed adapter on its own,
     * unless APCu is there to sit in front of it.
     *
     * @return class-string
     */
    private function systemCacheClass(): string
    {
        if (!ApcuAdapter::isSupported()) {
            return PhpFilesAdapter::class;
        }

        if (PHP_SAPI === 'cli' && !filter_var(ini_get('apc.enable_cli'), FILTER_VALIDATE_BOOL)) {
            return PhpFilesAdapter::class;
        }

        return ChainAdapter::class;
    }

    /**
     * Whether the definition is the debug decorator recording cache calls for the profiler.
     *
     * The pool it wraps is shared on purpose - an ArrayAdapter or whatever else backs the pool is meant
     * to be seen by every coroutine - but the decorator's own $calls is per-request profiling data, and
     * it is not an AbstractAdapter, so nothing above pools it. Leaving it shared is what has one
     * coroutine writing to another one's call log.
     *
     * @param class-string|null $className
     */
    private function isTraceableAdapter(?string $className): bool
    {
        return $className !== null
            && ($className === TraceableAdapter::class || is_subclass_of($className, TraceableAdapter::class));
    }

    /**
     * The traceable is reset with clearCalls() rather than reset(), which is the one thing that must not
     * be used here: TraceableAdapter::reset() hands the reset down to the pool it wraps first, and for
     * an ArrayAdapter - what FrameworkBundle gives you for `cache.adapter.array` - reset() is clear().
     * Attaching the obvious resetter would empty every cache in the application on the way out of every
     * single coroutine.
     */
    private function newResetter(string $resetMethod): Definition
    {
        $resetterDef = new Definition(SimpleResetter::class);
        $resetterDef->setArguments([$resetMethod]);

        return $resetterDef;
    }
}
