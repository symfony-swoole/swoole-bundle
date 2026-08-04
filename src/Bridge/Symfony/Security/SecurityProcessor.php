<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Security;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\Proxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Makes SecurityBundle's access decision manager coroutine-safe.
 *
 * AccessDecisionManager keeps the decision currently being made on a stack of its own, pushing on the
 * way into decide() and popping on the way out:
 *
 * ```php
 * $accessDecision ??= end($this->accessDecisionStack) ?: new AccessDecision();
 * $this->accessDecisionStack[] = $accessDecision;
 * try {
 *     return $accessDecision->isGranted = $this->strategy->decide(...);
 * } finally {
 *     array_pop($this->accessDecisionStack);
 * }
 * ```
 *
 * The service is shared, so under coroutines that one stack is shared too - and a voter only has to do
 * any I/O for its coroutine to be suspended half way through a decision, leaving the stack non-empty
 * while another request walks into decide() and pushes onto it. `end()` then hands that request the
 * decision belonging to the first one, and the pops come back in an order nobody intended.
 *
 * Symfony's TraceableAccessDecisionManager decorates the real one when kernel.debug is on. It is
 * resettable, so it gets pooled already - but pooling the decorator does nothing for the manager
 * underneath, which is what actually holds the stack. Hence the pair being handled together, in the same
 * shape EventDispatcherProcessor uses for the firewall dispatchers: the decorated instance is made
 * non-shared so every pooled decorator gets one of its own, and without a decorator the manager itself is
 * pooled directly.
 */
final class SecurityProcessor implements CompileProcessor
{
    private const string ACCESS_DECISION_MANAGER_ID = 'security.access.decision_manager';
    private const string DEBUG_ACCESS_DECISION_MANAGER_ID = 'debug.security.access.decision_manager';

    public function process(ContainerBuilder $container, Proxifier $proxifier): void
    {
        /** @var array<string,string> $bundles */
        $bundles = $container->getParameter('kernel.bundles');

        if (!isset($bundles['SecurityBundle'])) {
            return;
        }

        $this->processAccessDecisionManagerPair($container, $proxifier);
    }

    private function processAccessDecisionManagerPair(ContainerBuilder $container, Proxifier $proxifier): void
    {
        if (!$container->hasDefinition(self::ACCESS_DECISION_MANAGER_ID)) {
            return;
        }

        $decoratedId = self::DEBUG_ACCESS_DECISION_MANAGER_ID . '.inner';

        if (
            !$container->hasDefinition(self::DEBUG_ACCESS_DECISION_MANAGER_ID)
            || !$container->hasDefinition($decoratedId)
        ) {
            // no debug decorator: the manager holding the stack is the service everything depends on
            $proxifier->proxifyService(self::ACCESS_DECISION_MANAGER_ID);

            return;
        }

        // the decorator keeps a reference to the manager it wraps, so the two have to travel together:
        // one manager per pooled decorator rather than one shared by all of them
        $container->findDefinition($decoratedId)
            ->setShared(false);

        $container->getDefinition(self::DEBUG_ACCESS_DECISION_MANAGER_ID)
            ->addTag(ContainerConstants::TAG_STATEFUL_SERVICE);
    }
}
