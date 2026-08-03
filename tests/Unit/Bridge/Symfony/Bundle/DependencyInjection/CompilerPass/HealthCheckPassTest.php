<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\HealthCheckPass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Server\Configurator\WithHealthEvaluatorProcess;
use SwooleBundle\SwooleBundle\Server\Health\HealthReporter;
use SwooleBundle\SwooleBundle\Server\Health\HealthStatusTable;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\HealthCheck\FlaggedHealthCheck;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(HealthCheckPass::class)]
final class HealthCheckPassTest extends TestCase
{
    public function testWithoutRegisteredChecksNothingIsLeftToEvaluate(): void
    {
        $container = $this->containerWithHealthEnabled();

        (new HealthCheckPass())->process($container);

        self::assertFalse($container->hasDefinition(WithHealthEvaluatorProcess::class));
        self::assertFalse($container->hasDefinition(HealthStatusTable::class));
        self::assertNull($container->getDefinition(HealthReporter::class)->getArgument('$table'));
    }

    public function testTheStatusTableIsSizedToTheRegisteredChecks(): void
    {
        $container = $this->containerWithHealthEnabled();
        $container->register('check.one', FlaggedHealthCheck::class)
            ->addTag(ContainerConstants::TAG_HEALTH_CHECK);
        $container->register('check.two', FlaggedHealthCheck::class)
            ->addTag(ContainerConstants::TAG_HEALTH_CHECK);

        (new HealthCheckPass())->process($container);

        self::assertTrue($container->hasDefinition(WithHealthEvaluatorProcess::class));
        self::assertSame(2, $container->getDefinition(HealthStatusTable::class)->getArgument('$checkCount'));
    }

    public function testAContainerWithoutTheHealthEndpointIsLeftAlone(): void
    {
        $container = new ContainerBuilder();
        $container->register('check.one', FlaggedHealthCheck::class)
            ->addTag(ContainerConstants::TAG_HEALTH_CHECK);

        (new HealthCheckPass())->process($container);

        self::assertContains('check.one', array_keys($container->getDefinitions()));
        self::assertFalse($container->hasDefinition(HealthStatusTable::class));
    }

    private function containerWithHealthEnabled(): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $container->register(HealthStatusTable::class)
            ->setFactory([HealthStatusTable::class, 'forChecks'])
            ->setArgument('$checkCount', 0);

        $container->register(HealthReporter::class)
            ->setArgument('$table', new Reference(HealthStatusTable::class))
            ->setArgument('$stalenessThreshold', 15);

        $container->register(WithHealthEvaluatorProcess::class);

        return $container;
    }
}
