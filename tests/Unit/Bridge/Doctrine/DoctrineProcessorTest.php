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
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\{
    ClassModificationProcessor,
    Proxifier,
};
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

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

    private function process(ContainerBuilder $container): void
    {
        (new DoctrineProcessor())->process(
            $container,
            new Proxifier($container, new ClassModificationProcessor($container)),
        );
    }

    private function containerWithOneConnection(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => 'Doctrine\Bundle\DoctrineBundle']);
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('doctrine.entity_managers', []);
        $container->setParameter('doctrine.connections', ['default' => self::CONNECTION_SVC_ID]);

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
