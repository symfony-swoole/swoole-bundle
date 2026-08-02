<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\EventDispatcher;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\Proxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Symfony's SecurityBundle registers a dedicated EventDispatcher per firewall
 * (`security.event_dispatcher.<name>`, see SecurityExtension::createFirewall()) instead of reusing the
 * app-wide one, because firewall-scoped listeners (LazyFirewallContext, authenticator manager) must not
 * leak across firewalls. Each one is just as shared/singleton as the app-wide dispatcher and needs the
 * same per-coroutine treatment, otherwise concurrent requests through the same firewall collide on it.
 */
final class EventDispatcherProcessor implements CompileProcessor
{
    private const string SECURITY_FIREWALLS_PARAM = 'security.firewalls';
    private const string SECURITY_EVENT_DISPATCHER_ID_PREFIX = 'security.event_dispatcher.';

    public function process(ContainerBuilder $container, Proxifier $proxifier): void
    {
        $this->processDispatcherPair($container, $proxifier, 'event_dispatcher', 'debug.event_dispatcher');

        foreach ($this->firewallDispatcherIds($container) as $dispatcherId) {
            $this->processDispatcherPair($container, $proxifier, $dispatcherId, 'debug.' . $dispatcherId);
        }
    }

    /**
     * @return list<string>
     */
    private function firewallDispatcherIds(ContainerBuilder $container): array
    {
        if (!$container->hasParameter(self::SECURITY_FIREWALLS_PARAM)) {
            return [];
        }

        /** @var list<string> $firewallNames */
        $firewallNames = $container->getParameter(self::SECURITY_FIREWALLS_PARAM);
        $dispatcherIds = [];

        foreach ($firewallNames as $firewallName) {
            $dispatcherId = self::SECURITY_EVENT_DISPATCHER_ID_PREFIX . $firewallName;

            // By this stage Symfony's own DecoratorServicePass (PassConfig::TYPE_OPTIMIZE, which runs
            // before this pass) has already turned $dispatcherId into an alias pointing at
            // 'debug.' . $dispatcherId, for every firewall that has a debug wrapper — hasDefinition()
            // returns false for an alias, so it must not be used here, or every such firewall is
            // silently skipped and its dispatcher never gets made coroutine-safe. has() checks aliases
            // too, and still correctly excludes firewalls with no dispatcher at all (e.g. security:
            // false ones), which are neither a definition nor an alias.
            if (!$container->has($dispatcherId)) {
                continue;
            }

            $dispatcherIds[] = $dispatcherId;
        }

        return $dispatcherIds;
    }

    /**
     * When a debug wrapper decorates the dispatcher, the wrapper is what the rest of the app depends on
     * and gets pooled per-coroutine by StatefulServicesPass; its inner (real) dispatcher is made
     * non-shared so each pooled wrapper instance gets its own. Without a debug wrapper the dispatcher
     * service itself is the one everything depends on, so it is proxified (pooled per-coroutine)
     * directly instead.
     */
    private function processDispatcherPair(
        ContainerBuilder $container,
        Proxifier $proxifier,
        string $dispatcherId,
        string $debugDispatcherId,
    ): void {
        if (
            !$container->hasDefinition($debugDispatcherId)
            || !$container->hasDefinition($debugDispatcherId . '.inner')
        ) {
            $proxifier->proxifyService($dispatcherId);

            return;
        }

        // the debug event dispatcher needs to be coupled to the original event dispatcher, because
        // it registers listene≠rs to the original dispatcher
        $container->findDefinition($debugDispatcherId . '.inner')
            ->setShared(false);

        $debugDispatcherDef = $container->getDefinition($debugDispatcherId);
        $debugDispatcherDef->addTag(ContainerConstants::TAG_STATEFUL_SERVICE);
    }
}
