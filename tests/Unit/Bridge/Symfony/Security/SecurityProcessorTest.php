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
use SwooleBundle\SwooleBundle\Bridge\Symfony\Security\SecurityProcessor;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\ShouldBeProxified;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(SecurityProcessor::class)]
final class SecurityProcessorTest extends TestCase
{
    private const string MANAGER_ID = 'security.access.decision_manager';
    private const string DEBUG_MANAGER_ID = 'debug.security.access.decision_manager';

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
