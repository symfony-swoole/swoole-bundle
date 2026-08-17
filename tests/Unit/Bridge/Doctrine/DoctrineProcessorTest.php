<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Doctrine;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\ResetterBundle\DBAL\Connection\DBALPlatformAliveKeeper;
use SwooleBundle\ResetterBundle\DBAL\Connection\OptimizedDBALAliveKeeper;
use SwooleBundle\ResetterBundle\DBAL\Connection\PingingDBALAliveKeeper;
use SwooleBundle\SwooleBundle\Bridge\Doctrine\DBAL\CoroutinesOptimizedDBALAliveKeeper;
use SwooleBundle\SwooleBundle\Bridge\Doctrine\DoctrineProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\ServiceProxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\LazyGhostExample;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;

#[CoversClass(DoctrineProcessor::class)]
final class DoctrineProcessorTest extends TestCase
{
    private const string CONNECTION_SVC_ID = 'doctrine.dbal.default_connection';
    private const string ALIVE_KEEPER_SVC_ID = 'swoole_bundle_resetter.alive_keeper.dbal.default';

    /**
     * The resetter bundle's ping-interval decorator keeps the moment of the last ping in a plain int
     * property, which one shared keeper cannot do for a whole pool of connections without both mixing
     * their intervals up and being written from several coroutines at once.
     */
    public function testThePingIntervalDecoratorIsSwappedForItsCoroutineSafeCounterpart(): void
    {
        $container = $this->containerWithOneConnection();
        $container->register(self::ALIVE_KEEPER_SVC_ID, OptimizedDBALAliveKeeper::class)
            ->setArgument('$decorated', new Reference('inner_alive_keeper'))
            ->setArgument('$pingIntervalInSeconds', 30);

        $this->process($container);

        $aliveKeeperDef = $container->getDefinition(self::ALIVE_KEEPER_SVC_ID);

        self::assertSame(CoroutinesOptimizedDBALAliveKeeper::class, $aliveKeeperDef->getClass());
        self::assertSame(30, $aliveKeeperDef->getArgument('$pingIntervalInSeconds'));
        self::assertEquals(new Reference('inner_alive_keeper'), $aliveKeeperDef->getArgument('$decorated'));
    }

    /**
     * The decorator is only registered when a ping interval is configured; without one the plain,
     * always-pinging keeper stays in place untouched.
     */
    public function testAnAliveKeeperWithoutThePingIntervalDecoratorIsLeftAlone(): void
    {
        $container = $this->containerWithOneConnection();
        $container->register(self::ALIVE_KEEPER_SVC_ID, PingingDBALAliveKeeper::class);

        $this->process($container);

        self::assertSame(
            PingingDBALAliveKeeper::class,
            $container->getDefinition(self::ALIVE_KEEPER_SVC_ID)->getClass(),
        );
    }

    /**
     * The alive keeper of every connection is reached through the pool initializer the processor
     * registers for it, so the swap has to survive that wiring.
     */
    public function testTheSwappedKeeperIsTheOneEveryPooledConnectionIsInitializedWith(): void
    {
        $container = $this->containerWithOneConnection();
        $container->register(self::ALIVE_KEEPER_SVC_ID, OptimizedDBALAliveKeeper::class)
            ->setArgument('$decorated', new Reference('inner_alive_keeper'));

        $this->process($container);

        $connectionTag = $container->getDefinition(self::CONNECTION_SVC_ID)
            ->getTag('swoole_bundle.stateful_service');

        self::assertArrayHasKey('initializer', $connectionTag[0]);

        $initializerDef = $container->getDefinition($connectionTag[0]['initializer']);
        $keeperSvcId = (string) $initializerDef->getArgument(0);

        self::assertSame(self::ALIVE_KEEPER_SVC_ID, $keeperSvcId);
        self::assertSame(
            CoroutinesOptimizedDBALAliveKeeper::class,
            $container->getDefinition($keeperSvcId)->getClass(),
        );
    }

    /**
     * Adding the query log back to services_resetter is undone moments later without this tag:
     * StatefulServicesPass runs the compile processors and then calls reduceServiceResetters(), which
     * keeps only the services whose tag asks to be reset on each request. Missing it, the log is emptied
     * by nobody and grows for as long as the process lives - measured at ~10 MiB/min in a worker.
     */
    public function testTheQueryLogAsksToBeResetOnEachRequestSoTheReducerKeepsIt(): void
    {
        $container = $this->containerWithOneConnection();
        $container->register('doctrine.debug_data_holder', LazyGhostExample::class);
        $container->register('services_resetter', ServicesResetter::class)
            ->setArguments([new IteratorArgument([]), []]);

        $this->process($container);

        self::assertSame(
            [['reset_on_each_request' => true]],
            $container->getDefinition('doctrine.debug_data_holder')
                ->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    /**
     * The resetter still has to be told how to reset it, tag or no tag.
     */
    public function testTheQueryLogIsRegisteredWithTheResetterItsResetMethodIncluded(): void
    {
        $container = $this->containerWithOneConnection();
        $container->register('doctrine.debug_data_holder', LazyGhostExample::class);
        $container->register('services_resetter', ServicesResetter::class)
            ->setArguments([new IteratorArgument([]), []]);

        $this->process($container);

        $resetterDef = $container->getDefinition('services_resetter');
        /** @var IteratorArgument $resetters */
        $resetters = $resetterDef->getArgument(0);

        self::assertArrayHasKey('doctrine.debug_data_holder', $resetters->getValues());
        self::assertSame(['reset'], $resetterDef->getArgument(1)['doctrine.debug_data_holder']);
    }

    /**
     * A kernel.reset tag is how DoctrineBundle registers the log from 2.14 on, and it buys nothing on
     * its own: reduceServiceResetters() drops everything that is not asking to be reset on each
     * request, that tag included. Leaving such a log alone is what let it grow unchecked.
     */
    public function testAQueryLogAlreadyTaggedKernelResetStillAsksToBeResetOnEachRequest(): void
    {
        $container = $this->containerWithOneConnection();
        $container->register('doctrine.debug_data_holder', LazyGhostExample::class)
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->register('services_resetter', ServicesResetter::class)
            ->setArguments([new IteratorArgument([]), []]);

        $this->process($container);

        self::assertSame(
            [['reset_on_each_request' => true]],
            $container->getDefinition('doctrine.debug_data_holder')
                ->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    /**
     * It is already in services_resetter in that case, so there is nothing for the processor to add.
     */
    public function testAQueryLogAlreadyTaggedKernelResetIsNotAddedToTheResetterTwice(): void
    {
        $container = $this->containerWithOneConnection();
        $container->register('doctrine.debug_data_holder', LazyGhostExample::class)
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->register('services_resetter', ServicesResetter::class)
            ->setArguments([new IteratorArgument([]), []]);

        $this->process($container);

        /** @var IteratorArgument $resetters */
        $resetters = $container->getDefinition('services_resetter')->getArgument(0);

        self::assertSame([], $resetters->getValues());
    }

    /**
     * Pooling is the tag's job, not the processor's. StatefulServicesPass proxifies tagged services
     * after the compile processors, with the reset method it reads back out of services_resetter -
     * whereas anything proxified from in here predates that map and could only be given a null
     * resetter, which ServicePoolContainer skips.
     */
    public function testTheProcessorLeavesProxifyingTheQueryLogToTheTag(): void
    {
        $container = $this->containerWithOneConnection();
        $container->register('doctrine.debug_data_holder', LazyGhostExample::class);
        $container->register('services_resetter', ServicesResetter::class)
            ->setArguments([new IteratorArgument([]), []]);

        $proxifier = $this->createMock(ServiceProxifier::class);
        $proxifier->expects(self::never())->method('proxifyService');

        (new DoctrineProcessor())->process($container, $proxifier);
    }

    private function process(ContainerBuilder $container): void
    {
        // A double, not the real Proxifier: proxifying reads the class through z-engine, which
        // initializes once per PHP process and would settle that for every test running after this
        // one. What the processor does to the container is the whole subject here; that the proxy
        // itself works is what tests/Feature is for.
        (new DoctrineProcessor())->process($container, $this->createStub(ServiceProxifier::class));
    }

    private function containerWithOneConnection(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => 'Doctrine\Bundle\DoctrineBundle']);
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('doctrine.entity_managers', []);
        $container->setParameter('doctrine.connections', ['default' => self::CONNECTION_SVC_ID]);
        // only reached by the tests that get as far as proxifying something
        $container->setParameter(ContainerConstants::PARAM_COROUTINES_MAX_SVC_INSTANCES, 20);

        $container->register('doctrine', 'Doctrine\Bundle\DoctrineBundle\Registry');
        $container->register(self::CONNECTION_SVC_ID, Connection::class);
        $container->register(self::CONNECTION_SVC_ID . '.event_manager', EventManager::class);
        $container->register('inner_alive_keeper', PingingDBALAliveKeeper::class);
        $container->register(DBALPlatformAliveKeeper::class, DBALPlatformAliveKeeper::class)
            ->setArgument(0, [])
            ->setArgument(1, ['default' => new Reference(self::ALIVE_KEEPER_SVC_ID)]);

        return $container;
    }
}
