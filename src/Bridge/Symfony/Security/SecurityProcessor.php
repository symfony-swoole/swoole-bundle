<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Security;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\Proxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Bundle\SecurityBundle\Security\LazyFirewallContext;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Security\Http\Authenticator\Debug\TraceableAuthenticator;
use Symfony\Component\Security\Http\Firewall\ContextListener;

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
    private const string DEBUG_FIREWALL_LISTENER_ID = 'debug.security.firewall';
    private const string CONTEXT_LISTENER_RESETTER_ID =
        'swoole_bundle.coroutines_support.security.context_listener_resetter';

    public function process(ContainerBuilder $container, Proxifier $proxifier): void
    {
        /** @var array<string,string> $bundles */
        $bundles = $container->getParameter('kernel.bundles');

        if (!isset($bundles['SecurityBundle'])) {
            return;
        }

        $this->processAccessDecisionManagerPair($container, $proxifier);
        $this->processLazyFirewallContexts($container);
        $this->processContextListeners($container);
        $this->processTraceableAuthenticators($container);
    }

    /**
     * Gives every coroutine its own copy of the profiler's authenticator decorators.
     *
     * TraceableAuthenticator wraps each configured authenticator when kernel.debug is on, and records what
     * it saw on itself as the request goes through - whether the authenticator supported the request, the
     * passport it produced, how long it took, whether it threw:
     *
     * ```php
     * public function supports(Request $request): ?bool
     * {
     *     return $this->supports = $this->authenticator->supports($request);
     * }
     * ```
     *
     * One instance serves the whole worker, so those writes land on an object every other request is
     * reading from, and the security panel ends up describing a request that never happened.
     *
     * SecurityBundle registers one per authenticator per firewall, under ids built from their names, so
     * there is nothing to list - they are found by what they are instead.
     */
    private function processTraceableAuthenticators(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $definition) {
            if ($definition->isAbstract() || $definition->getClass() !== TraceableAuthenticator::class) {
                continue;
            }

            if ($this->hasAbstractArguments($definition)) {
                continue;
            }

            $definition->addTag(ContainerConstants::TAG_STATEFUL_SERVICE);
        }
    }

    /**
     * Gives every coroutine a context listener of its own, and puts it back the way it was found.
     *
     * The listener registers itself on the firewall's dispatcher on the way into a request and takes
     * itself off again on the way out, remembering in between that it has done so:
     *
     * ```php
     * if (!$this->registered && null !== $this->dispatcher && $event->isMainRequest()) {
     *     $this->dispatcher->addListener(KernelEvents::RESPONSE, $this->onKernelResponse(...));
     *     $this->registered = true;
     * }
     * ```
     *
     * Shared by a worker, that flag is read and written by every coroutine at once - which is what the
     * concurrency check trips over. The quieter half is what the flag then does: the dispatchers are
     * already pooled per coroutine, so a request finding the flag already set by somebody else skips
     * registering on its own dispatcher, and nothing writes its security token back to the session.
     *
     * A resetter is needed as well as the pooling, because the flag is not always cleared - see
     * {@see ContextListenerResetter}.
     */
    private function processContextListeners(ContainerBuilder $container): void
    {
        $tagged = 0;

        foreach ($container->getDefinitions() as $definition) {
            if ($definition->isAbstract() || $definition->getClass() !== ContextListener::class) {
                continue;
            }

            // SecurityBundle keeps a template listener around for the per-firewall ones to be built from,
            // and leaves the arguments only a firewall can fill in - the provider key, here - standing as
            // abstract. It is not marked abstract itself, so there is nothing else to tell it apart, and
            // pooling something that cannot be instantiated fails the container build.
            if ($this->hasAbstractArguments($definition)) {
                continue;
            }

            $definition->addTag(
                ContainerConstants::TAG_STATEFUL_SERVICE,
                ['resetter' => self::CONTEXT_LISTENER_RESETTER_ID],
            );
            $tagged++;
        }

        if ($tagged === 0) {
            return;
        }

        $container->setDefinition(self::CONTEXT_LISTENER_RESETTER_ID, new Definition(ContextListenerResetter::class));
    }

    private function hasAbstractArguments(Definition $definition): bool
    {
        foreach ($definition->getArguments() as $argument) {
            if ($argument instanceof AbstractArgument) {
                return true;
            }
        }

        return false;
    }

    /**
     * Stops the firewall contexts from being shared, so that the profiler rewriting their listeners can
     * only ever reach the one belonging to the request doing the rewriting.
     *
     * With kernel.debug on, TraceableFirewallListener replaces every listener of a LazyFirewallContext with
     * a WrappedLazyListener that times it, and writes the replacements straight back onto the context:
     *
     * ```php
     * \Closure::bind(function () use (&$contextWrappedListeners) {
     *     foreach ($this->listeners as $listener) {
     *         $contextWrappedListeners[] = new WrappedLazyListener($listener);
     *     }
     *     $this->listeners = $contextWrappedListeners;
     * }, $listener, FirewallContext::class)();
     * ```
     *
     * That is written for a container built fresh for one request. In a worker the context is one shared
     * service, so the write lands on an object every other coroutine is reading its listeners from - and
     * the rewriting is cumulative besides, each request wrapping the wrappers the last one left behind
     * until a request is running through a stack of them.
     *
     * Pooling would answer the first half and not the second: a pooled context is reused by later requests
     * and keeps whatever was written onto it, and the original listeners cannot be put back because they
     * were overwritten. Handing out a new context per request instead is both what Symfony assumes and the
     * cheaper thing to do - the listeners are services of their own, so all that gets built is the context
     * object holding them.
     *
     * Only the lazy contexts are touched, because they are the only ones handed to the firewall listener as
     * listeners themselves, and so the only ones it rewrites. Only with the profiler on, because nothing
     * writes to them otherwise.
     */
    private function processLazyFirewallContexts(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::DEBUG_FIREWALL_LISTENER_ID)) {
            return;
        }

        foreach ($container->getDefinitions() as $definition) {
            // The per-firewall contexts are child definitions of an abstract parent, long since resolved
            // into standalone definitions carrying the class by the time compile processors run - so the
            // class is there to be compared, and comparing it is all this does. Asking whether it is a
            // LazyFirewallContext instead would autoload every service class in the container on the way
            // past, and a container is free to hold definitions for classes that cannot be loaded at all
            // (Doctrine's UniqueEntityValidator without symfony/validator, for one).
            if ($definition->isAbstract() || $definition->getClass() !== LazyFirewallContext::class) {
                continue;
            }

            $definition->setShared(false);
        }
    }

    private function processAccessDecisionManagerPair(ContainerBuilder $container, Proxifier $proxifier): void
    {
        $decoratedId = self::DEBUG_ACCESS_DECISION_MANAGER_ID . '.inner';

        // DecoratorServicePass has already run by the time compile processors do, and it leaves the
        // decorator answering to its own id with the decorated manager moved to `.inner` - while
        // `security.access.decision_manager` becomes a plain alias to the decorator. Going by the
        // presence of `.inner` is what tells the two shapes apart; asking hasDefinition() about an
        // alias only ever answers false.
        if ($container->hasDefinition($decoratedId)) {
            // the decorator holds on to the manager it wraps, so the two have to travel together: one
            // manager per pooled decorator instead of one shared by all of them
            $container->getDefinition($decoratedId)
                ->setShared(false);

            // the decorator is resettable, so it is pooled for that alone - saying so outright keeps it
            // pooled even if that ever stops being true
            $container->getDefinition(self::DEBUG_ACCESS_DECISION_MANAGER_ID)
                ->addTag(ContainerConstants::TAG_STATEFUL_SERVICE);

            return;
        }

        if (!$container->hasDefinition(self::ACCESS_DECISION_MANAGER_ID)) {
            return;
        }

        // no debug decorator: the manager holding the stack is the service everything depends on
        $proxifier->proxifyService(self::ACCESS_DECISION_MANAGER_ID);
    }
}
