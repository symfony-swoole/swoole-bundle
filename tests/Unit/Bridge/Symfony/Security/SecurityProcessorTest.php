<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Security;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\{
    ClassModificationProcessor,
    Proxifier,
};
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Security\ContextListenerResetter;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Security\SecurityProcessor;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\ShouldBeProxified;
use Symfony\Bundle\SecurityBundle\Debug\TraceableFirewallListener;
use Symfony\Bundle\SecurityBundle\Security\FirewallContext;
use Symfony\Bundle\SecurityBundle\Security\LazyFirewallContext;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Http\Firewall\ContextListener;

#[CoversClass(SecurityProcessor::class)]
final class SecurityProcessorTest extends TestCase
{
    private const string MANAGER_ID = 'security.access.decision_manager';
    private const string DEBUG_MANAGER_ID = 'debug.security.access.decision_manager';
    private const string CONTEXT_LISTENER_RESETTER_ID =
        'swoole_bundle.coroutines_support.security.context_listener_resetter';

    /**
     * With kernel.debug on, TraceableAccessDecisionManager decorates the real manager and is pooled for
     * being resettable - but it holds a reference to the manager underneath, which is the one keeping the
     * decision stack. Making that one non-shared is what gives every pooled decorator a manager of its
     * own instead of all of them sharing a single stack.
     */
    public function testTheDecoratedManagerBecomesNonSharedAndTheDecoratorIsPooled(): void
    {
        $container = $this->containerWithSecurity(withDebugDecorator: true);

        $this->process($container);

        self::assertFalse(
            $container->getDefinition(self::DEBUG_MANAGER_ID . '.inner')->isShared(),
            'The decorated manager must not be shared by every pooled decorator.'
        );
        self::assertArrayHasKey(
            ContainerConstants::TAG_STATEFUL_SERVICE,
            $container->getDefinition(self::DEBUG_MANAGER_ID)->getTags(),
        );
    }

    public function testAnApplicationWithoutSecurityBundleIsLeftAlone(): void
    {
        $container = $this->containerWithSecurity(withDebugDecorator: true);
        $container->setParameter('kernel.bundles', []);

        $this->process($container);

        self::assertTrue($container->getDefinition(self::DEBUG_MANAGER_ID . '.inner')->isShared());
        self::assertSame([], $container->getDefinition(self::DEBUG_MANAGER_ID)->getTags());
    }

    /**
     * SecurityBundle can be registered without the access decision manager ever being built - a firewall
     * -less configuration, for instance - and the processor has nothing to say about that.
     */
    public function testAContainerWithoutTheManagerIsLeftAlone(): void
    {
        $container = $this->newContainer();

        $this->process($container);

        self::assertFalse($container->hasDefinition(self::MANAGER_ID));
    }

    /**
     * The profiler's firewall listener rewrites a lazy context's listeners in place, once per request. A
     * context shared by the whole worker is one every coroutine reads its listeners from while somebody
     * else writes them, and one that keeps every rewrite ever made to it.
     */
    public function testLazyFirewallContextsStopBeingSharedWithTheProfilerOn(): void
    {
        $container = $this->containerWithFirewalls(withProfiler: true);

        $this->process($container);

        self::assertFalse($container->getDefinition('security.firewall.map.context.main')->isShared());
        self::assertFalse($container->getDefinition('security.firewall.map.context.api')->isShared());
    }

    /**
     * The eager contexts are never handed to the firewall listener as listeners, so nothing rewrites them.
     */
    public function testEagerFirewallContextsAreLeftShared(): void
    {
        $container = $this->containerWithFirewalls(withProfiler: true);

        $this->process($container);

        self::assertTrue($container->getDefinition('security.firewall.map.context.stateless')->isShared());
    }

    public function testTheAbstractParentDefinitionIsLeftAlone(): void
    {
        $container = $this->containerWithFirewalls(withProfiler: true);

        $this->process($container);

        self::assertTrue($container->getDefinition('security.firewall.lazy_context')->isShared());
    }

    /**
     * Without the profiler nothing writes to a context, and sharing them is what Symfony intends.
     */
    public function testLazyFirewallContextsAreLeftSharedWithoutTheProfiler(): void
    {
        $container = $this->containerWithFirewalls(withProfiler: false);

        $this->process($container);

        self::assertTrue($container->getDefinition('security.firewall.map.context.main')->isShared());
    }

    /**
     * The context listener remembers having registered itself on the firewall's dispatcher. Shared, that
     * flag is written by every coroutine at once - and a request finding it already set skips registering
     * on its own dispatcher, so nothing writes its token back to the session.
     */
    public function testContextListenersArePooledWithAResetterOfTheirOwn(): void
    {
        $container = $this->containerWithFirewalls(withProfiler: true);

        $this->process($container);

        self::assertSame(
            [['resetter' => self::CONTEXT_LISTENER_RESETTER_ID]],
            $container->getDefinition('security.context_listener.0')
                ->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    /**
     * The flag survives a request that never reaches the point of clearing it, so pooling alone would
     * carry it into every later request the instance serves.
     */
    public function testTheContextListenerPoolIsResetOnRelease(): void
    {
        $container = $this->containerWithFirewalls(withProfiler: true);

        $this->process($container);

        self::assertSame(
            ContextListenerResetter::class,
            $container->getDefinition(self::CONTEXT_LISTENER_RESETTER_ID)->getClass(),
        );
    }

    /**
     * SecurityBundle keeps a template context listener for the per-firewall ones to be built from, with
     * the provider key left standing as an abstract argument. Pooling it fails the container build.
     */
    public function testTheTemplateContextListenerIsLeftAlone(): void
    {
        $container = $this->containerWithFirewalls(withProfiler: true);
        $container->register('security.context_listener', ContextListener::class)
            ->setArguments([new AbstractArgument('Provider Key')]);

        $this->process($container);

        self::assertSame(
            [],
            $container->getDefinition('security.context_listener')
                ->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    public function testNoResetterIsRegisteredWithoutAContextListener(): void
    {
        $container = $this->containerWithSecurity(withDebugDecorator: true);

        $this->process($container);

        self::assertFalse($container->hasDefinition(self::CONTEXT_LISTENER_RESETTER_ID));
    }

    private function containerWithFirewalls(bool $withProfiler): ContainerBuilder
    {
        $container = $this->containerWithSecurity(withDebugDecorator: true);

        if ($withProfiler) {
            $container->register('debug.security.firewall', TraceableFirewallListener::class);
        }

        $container->register('security.firewall.lazy_context', LazyFirewallContext::class)
            ->setAbstract(true);

        foreach (['main', 'api'] as $firewall) {
            $container->register(sprintf('security.firewall.map.context.%s', $firewall), LazyFirewallContext::class);
        }

        $container->register('security.firewall.map.context.stateless', FirewallContext::class);
        $container->register('security.context_listener.0', ContextListener::class);

        return $container;
    }

    private function process(ContainerBuilder $container): void
    {
        (new SecurityProcessor())->process(
            $container,
            new Proxifier($container, new ClassModificationProcessor($container)),
        );
    }

    private function newContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => 'Symfony\Bundle\SecurityBundle']);
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter(ContainerConstants::PARAM_COROUTINES_MAX_SVC_INSTANCES, 20);

        return $container;
    }

    private function containerWithSecurity(bool $withDebugDecorator): ContainerBuilder
    {
        $container = $this->newContainer();

        // any non-final class will do: the processor goes by service id, exactly as SecurityBundle
        // registers them, and never looks at what the definitions are made of
        if (!$withDebugDecorator) {
            $container->register(self::MANAGER_ID, ShouldBeProxified::class);

            return $container;
        }

        // The shape DecoratorServicePass leaves behind, which is not the obvious one: the decorator
        // keeps its own id and the manager it decorates moves to `.inner`, while the id everything else
        // refers to - security.access.decision_manager - is turned into an alias. hasDefinition() answers
        // false for an alias, so a processor guarding on it walks straight past the whole thing.
        $container->register(self::DEBUG_MANAGER_ID . '.inner', ShouldBeProxified::class);
        $container->register(self::DEBUG_MANAGER_ID, ShouldBeProxified::class)
            ->setArguments([new Reference(self::DEBUG_MANAGER_ID . '.inner')]);
        $container->setAlias(self::MANAGER_ID, self::DEBUG_MANAGER_ID);

        return $container;
    }
}
