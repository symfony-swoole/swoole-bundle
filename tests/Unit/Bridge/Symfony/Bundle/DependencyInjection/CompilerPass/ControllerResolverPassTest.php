<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\ControllerResolverPass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel\Controller\NonMutatingControllerResolver;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(ControllerResolverPass::class)]
final class ControllerResolverPassTest extends TestCase
{
    private const string SERVICE_ID = 'controller_resolver';

    public function testTheResolverIsSwappedWhenCoroutinesAreEnabled(): void
    {
        $container = $this->containerWithSymfonyResolver(coroutinesEnabled: true);

        (new ControllerResolverPass())->process($container);

        self::assertSame(
            NonMutatingControllerResolver::class,
            $container->getDefinition(self::SERVICE_ID)->getClass(),
        );
    }

    /**
     * Without coroutines the per-request writes are harmless, so there is nothing to fix and no reason
     * to step in front of Symfony's own resolver.
     */
    public function testTheResolverIsLeftAloneWithoutCoroutines(): void
    {
        $container = $this->containerWithSymfonyResolver(coroutinesEnabled: false);

        (new ControllerResolverPass())->process($container);

        self::assertSame(ControllerResolver::class, $container->getDefinition(self::SERVICE_ID)->getClass());
    }

    /**
     * Everything FrameworkBundle configured on the definition has to survive the swap - the replacement
     * only drops the writes, it does not re-do the wiring.
     */
    public function testTheDefinitionIsOtherwiseUntouched(): void
    {
        $container = $this->containerWithSymfonyResolver(coroutinesEnabled: true);

        (new ControllerResolverPass())->process($container);

        $definition = $container->getDefinition(self::SERVICE_ID);

        self::assertEquals(
            [new Reference('service_container'), new Reference('logger')],
            $definition->getArguments(),
        );
        self::assertSame([['allowControllers', [['Some\Controller']]]], $definition->getMethodCalls());
        self::assertArrayHasKey('monolog.logger', $definition->getTags());
    }

    /**
     * If something else already replaced the resolver, it owns that slot - overwriting it would silently
     * drop whatever it does.
     */
    public function testAResolverReplacedBySomebodyElseIsNotOverwritten(): void
    {
        $container = $this->containerWithSymfonyResolver(coroutinesEnabled: true);
        $container->getDefinition(self::SERVICE_ID)->setClass('App\OwnControllerResolver');

        (new ControllerResolverPass())->process($container);

        self::assertSame('App\OwnControllerResolver', $container->getDefinition(self::SERVICE_ID)->getClass());
    }

    public function testAContainerWithoutTheResolverIsLeftAlone(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(ContainerConstants::PARAM_COROUTINES_ENABLED, true);

        (new ControllerResolverPass())->process($container);

        self::assertFalse($container->hasDefinition(self::SERVICE_ID));
    }

    private function containerWithSymfonyResolver(bool $coroutinesEnabled): ContainerBuilder
    {
        $container = new ContainerBuilder();

        if ($coroutinesEnabled) {
            $container->setParameter(ContainerConstants::PARAM_COROUTINES_ENABLED, true);
        }

        $container->register(self::SERVICE_ID, ControllerResolver::class)
            ->setArguments([new Reference('service_container'), new Reference('logger')])
            ->addMethodCall('allowControllers', [['Some\Controller']])
            ->addTag('monolog.logger', ['channel' => 'request']);

        return $container;
    }
}
