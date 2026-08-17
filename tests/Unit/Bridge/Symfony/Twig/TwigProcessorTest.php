<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\{
    ClassModificationProcessor,
    Proxifier,
};
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Twig\TwigProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Twig\UnwrappingTwigDataCollector;
use Symfony\Bridge\Twig\DataCollector\TwigDataCollector;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Profiler\Profile;

#[CoversClass(TwigProcessor::class)]
final class TwigProcessorTest extends TestCase
{
    private const string PROFILE_ID = 'twig.profile';
    private const string DATA_COLLECTOR_ID = 'data_collector.twig';

    public function testTheProfileIsPooled(): void
    {
        $container = $this->containerWithTwigProfiler(withCollector: true);

        $this->process($container);

        self::assertSame(
            [[]],
            $container->getDefinition(self::PROFILE_ID)->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    /**
     * Pooling the profile hands the collector a proxy, and serialize() writes the class of whatever
     * object it is given - which the collector's own allow list then refuses to read back.
     */
    public function testTheCollectorIsReplacedWithTheOneThatStoresTheProfileUnwrapped(): void
    {
        $container = $this->containerWithTwigProfiler(withCollector: true);

        $this->process($container);

        self::assertSame(
            UnwrappingTwigDataCollector::class,
            $container->getDefinition(self::DATA_COLLECTOR_ID)->getClass(),
        );
    }

    /**
     * The profiler extension can be on with the profiler itself off, and then nothing serializes the
     * profile - there is no collector to replace and no reason to look for one.
     */
    public function testAnApplicationCollectingNothingKeepsItsProfilePooledAnyway(): void
    {
        $container = $this->containerWithTwigProfiler(withCollector: false);

        $this->process($container);

        self::assertSame(
            [[]],
            $container->getDefinition(self::PROFILE_ID)->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
        self::assertFalse($container->hasDefinition(self::DATA_COLLECTOR_ID));
    }

    /**
     * Without the Twig profiler there is no profile at all, and the collector is not registered either.
     */
    public function testAnApplicationWithoutTheTwigProfilerIsLeftAlone(): void
    {
        $container = $this->newContainer();
        $container->register(self::DATA_COLLECTOR_ID, TwigDataCollector::class);

        $this->process($container);

        self::assertSame(
            TwigDataCollector::class,
            $container->getDefinition(self::DATA_COLLECTOR_ID)->getClass(),
        );
    }

    private function containerWithTwigProfiler(bool $withCollector): ContainerBuilder
    {
        $container = $this->newContainer();
        $container->register(self::PROFILE_ID, Profile::class);

        if ($withCollector) {
            $container->register(self::DATA_COLLECTOR_ID, TwigDataCollector::class);
        }

        return $container;
    }

    private function process(ContainerBuilder $container): void
    {
        (new TwigProcessor())->process(
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
