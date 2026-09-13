<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Mercure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\{
    ClassModificationProcessor,
    Proxifier,
};
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Mercure\MercureProcessor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Mercure\Debug\TraceableHub;
use Symfony\Component\Mercure\Hub;

#[CoversClass(MercureProcessor::class)]
final class MercureProcessorTest extends TestCase
{
    private const string TRACEABLE_HUB_ID = 'mercure.hub.default.traceable';

    public function testTheTracedHubIsPooled(): void
    {
        $container = $this->newContainer();
        $container->register(self::TRACEABLE_HUB_ID, TraceableHub::class);

        $this->process($container);

        self::assertSame(
            [[]],
            $container->getDefinition(self::TRACEABLE_HUB_ID)->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    /**
     * An application naming several hubs gets a traced hub for each, and every one of them keeps a trace of
     * its own.
     */
    public function testEveryTracedHubInTheContainerIsPooled(): void
    {
        $container = $this->newContainer();
        $container->register(self::TRACEABLE_HUB_ID, TraceableHub::class);
        $container->register('mercure.hub.notifications.traceable', TraceableHub::class);

        $this->process($container);

        foreach ([self::TRACEABLE_HUB_ID, 'mercure.hub.notifications.traceable'] as $id) {
            self::assertSame(
                [[]],
                $container->getDefinition($id)->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
                sprintf('Expected %s to be pooled.', $id),
            );
        }
    }

    /**
     * Only the wrapper accumulates anything. The hub it decorates keeps nothing between publishes, and
     * pooling it would multiply its http client for no benefit.
     */
    public function testTheHubItDecoratesIsLeftAlone(): void
    {
        $container = $this->newContainer();
        $container->register(self::TRACEABLE_HUB_ID . '.inner', Hub::class);

        $this->process($container);

        self::assertSame(
            [],
            $container->getDefinition(self::TRACEABLE_HUB_ID . '.inner')
                ->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    /**
     * A container holds definitions for classes the application cannot load - an optional integration whose
     * package is not installed. Asking each definition what its class *is* rather than comparing the name
     * would autoload all of them, and the first one that will not load takes the compilation down with it.
     */
    public function testADefinitionNamingAnUnloadableClassDoesNotBreakTheCompilation(): void
    {
        $container = $this->newContainer();
        $container->register('some.optional.integration', 'App\\Vendor\\That\\Is\\Not\\Installed');
        $container->register(self::TRACEABLE_HUB_ID, TraceableHub::class);

        $this->process($container);

        self::assertTrue(
            $container->getDefinition(self::TRACEABLE_HUB_ID)->hasTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    /**
     * Without the profiler MercureBundle registers no traced hub, and without MercureBundle there is no hub at
     * all - both are ordinary applications, not errors.
     */
    public function testAnApplicationWithoutATracedHubIsLeftAlone(): void
    {
        $container = $this->newContainer();

        $this->expectNotToPerformAssertions();

        $this->process($container);
    }

    /**
     * Tagging twice would have StatefulServicesPass read a second, identical tag off the definition -
     * harmless, but the processor should be safe to run over a container it has already seen.
     */
    public function testAHubThatIsAlreadyPooledIsNotTaggedAgain(): void
    {
        $container = $this->newContainer();
        $container->register(self::TRACEABLE_HUB_ID, TraceableHub::class)
            ->addTag(ContainerConstants::TAG_STATEFUL_SERVICE, ['limit' => 5]);

        $this->process($container);

        self::assertSame(
            [['limit' => 5]],
            $container->getDefinition(self::TRACEABLE_HUB_ID)->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    private function process(ContainerBuilder $container): void
    {
        (new MercureProcessor())->process(
            $container,
            new Proxifier($container, new ClassModificationProcessor($container)),
        );
    }

    private function newContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter(ContainerConstants::PARAM_COROUTINES_MAX_SVC_INSTANCES, 20);

        return $container;
    }
}
