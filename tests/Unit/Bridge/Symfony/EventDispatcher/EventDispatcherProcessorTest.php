<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\EventDispatcher;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\{
    ClassModificationProcessor,
    Proxifier,
};
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\EventDispatcher\EventDispatcherProcessor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;

final class EventDispatcherProcessorTest extends TestCase
{
    public function testAppWideDebugDispatcherInnerIsMadeNonSharedAndWrapperIsTaggedStateful(): void
    {
        $container = $this->newContainer();
        $this->registerDebugWrappedDispatcher($container, 'event_dispatcher', 'debug.event_dispatcher');

        (new EventDispatcherProcessor())->process($container, $this->newProxifier($container));

        self::assertFalse($container->getDefinition('debug.event_dispatcher.inner')->isShared());
        self::assertTrue(
            $container->getDefinition('debug.event_dispatcher')->hasTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    /**
     * SecurityBundle registers a dedicated dispatcher per firewall (security.event_dispatcher.<name>)
     * instead of reusing the app-wide one; each one is just as shared/singleton and needs the same
     * per-coroutine treatment or concurrent requests through the same firewall collide on it.
     */
    public function testEachConfiguredFirewallDispatcherIsProcessedTheSameWay(): void
    {
        $container = $this->newContainer();
        $container->setParameter('security.firewalls', ['main', 'api']);
        $this->registerDebugWrappedDispatcher($container, 'event_dispatcher', 'debug.event_dispatcher');
        $this->registerDebugWrappedDispatcher(
            $container,
            'security.event_dispatcher.main',
            'debug.security.event_dispatcher.main',
        );
        $this->registerDebugWrappedDispatcher(
            $container,
            'security.event_dispatcher.api',
            'debug.security.event_dispatcher.api',
        );

        (new EventDispatcherProcessor())->process($container, $this->newProxifier($container));

        foreach (['main', 'api'] as $firewallName) {
            $debugDispatcherId = 'debug.security.event_dispatcher.' . $firewallName;

            self::assertFalse($container->getDefinition($debugDispatcherId . '.inner')->isShared());
            self::assertTrue(
                $container->getDefinition($debugDispatcherId)->hasTag(ContainerConstants::TAG_STATEFUL_SERVICE),
            );
        }
    }

    /**
     * A firewall listed in security.firewalls without its own dispatcher definition (e.g. a firewall
     * that shares another one's context) must be skipped rather than causing a missing-definition error.
     */
    public function testFirewallsWithoutTheirOwnDispatcherDefinitionAreSkipped(): void
    {
        $container = $this->newContainer();
        $container->setParameter('security.firewalls', ['dev']);
        $this->registerDebugWrappedDispatcher($container, 'event_dispatcher', 'debug.event_dispatcher');

        (new EventDispatcherProcessor())->process($container, $this->newProxifier($container));

        self::assertFalse($container->has('security.event_dispatcher.dev'));
    }

    /**
     * Mirrors what Symfony's own DecoratorServicePass does once it processes a decorated service: the
     * original id ($dispatcherId) becomes an ALIAS to the decorator, not a definition, and the original
     * definition is relocated to "$debugDispatcherId.inner". A container built with setDefinition()
     * for $dispatcherId directly (as an earlier version of this fixture did) doesn't reproduce that,
     * and let a regression slip through where firewallDispatcherIds() checked hasDefinition() on an id
     * that, post-decoration, is only ever an alias — silently skipping every firewall.
     */
    private function registerDebugWrappedDispatcher(
        ContainerBuilder $container,
        string $dispatcherId,
        string $debugDispatcherId,
    ): void {
        $container->setAlias($dispatcherId, $debugDispatcherId);
        $container->setDefinition($debugDispatcherId, new Definition(TraceableEventDispatcher::class));
        $container->setDefinition($debugDispatcherId . '.inner', new Definition(EventDispatcher::class));
    }

    private function newContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());

        return $container;
    }

    private function newProxifier(ContainerBuilder $container): Proxifier
    {
        return new Proxifier($container, new ClassModificationProcessor($container));
    }
}
