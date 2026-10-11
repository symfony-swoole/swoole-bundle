<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use PDO;
use PHPUnit\Framework\TestCase;
use SwooleBundle\ResetterBundle\Connection\ConnectionsHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\PdoSessionKeepAlivePass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession\PdoConnectionFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession\PDOKeepAliveInitializer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession\PDOPlatformAliveKeeper;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession\PingingPDOAliveKeeper;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class PdoSessionKeepAlivePassTest extends TestCase
{
    private const string PDO = 'app.session.pdo';
    private const string ALIVE_KEEPER = 'swoole_bundle.session.pdo.alive_keeper.app.session.pdo';
    private const string INITIALIZER = 'swoole_bundle.session.pdo.keep_alive_initializer.app.session.pdo';

    public function testAConnectionOfTheFactoryGetsAnAliveKeeperThatKnowsWhatItWasConstructedWith(): void
    {
        $container = $this->containerWithAConnection();

        (new PdoSessionKeepAlivePass())->process($container);

        $aliveKeeper = $container->getDefinition(self::ALIVE_KEEPER);
        $this->assertSame(PingingPDOAliveKeeper::class, $aliveKeeper->getClass());
        $arguments = $aliveKeeper->getArgument(0);
        $this->assertInstanceOf(Definition::class, $arguments);
        $this->assertSame(
            [PdoConnectionFactory::class, 'constructorArgumentsFromDsnOrUrl'],
            $arguments->getFactory(),
        );
        $this->assertSame(['sqlite:///:memory:'], $arguments->getArguments());
    }

    public function testWithCoroutinesAPooledConnectionIsInitializedAsItIsHandedOut(): void
    {
        $container = $this->containerWithAConnection(coroutines: true, pooled: true);

        (new PdoSessionKeepAlivePass())->process($container);

        $this->assertSame(
            [['limit' => 5, 'initializer' => self::INITIALIZER]],
            $container->getDefinition(self::PDO)->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
        $initializer = $container->getDefinition(self::INITIALIZER);
        $this->assertSame(PDOKeepAliveInitializer::class, $initializer->getClass());
        $this->assertEquals([new Reference(self::ALIVE_KEEPER), self::PDO], $initializer->getArguments());
        // The resetter bundle's request cycle is not how a coroutine's connection is looked after.
        $this->assertFalse($container->hasDefinition(PdoSessionKeepAlivePass::CONNECTIONS_HANDLER_SERVICE_ID));
    }

    public function testAnInitializerOfTheApplicationsOwnIsLeftInPlace(): void
    {
        $container = $this->containerWithAConnection(coroutines: true, pooled: true);
        $container->getDefinition(self::PDO)
            ->clearTag(ContainerConstants::TAG_STATEFUL_SERVICE)
            ->addTag(ContainerConstants::TAG_STATEFUL_SERVICE, ['initializer' => 'app.my_initializer']);

        (new PdoSessionKeepAlivePass())->process($container);

        $this->assertSame(
            [['initializer' => 'app.my_initializer']],
            $container->getDefinition(self::PDO)->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
        $this->assertFalse($container->hasDefinition(self::INITIALIZER));
    }

    public function testWithCoroutinesAConnectionThatIsNotPooledIsNobodysToInitialize(): void
    {
        $container = $this->containerWithAConnection(coroutines: true);

        (new PdoSessionKeepAlivePass())->process($container);

        $this->assertFalse($container->hasDefinition(self::INITIALIZER));
        $this->assertSame([], $container->getDefinition(self::PDO)->getTags());
    }

    public function testWithoutCoroutinesTheResetterBundleLooksAfterItAsARequestStarts(): void
    {
        $container = $this->containerWithAConnection(resetterBundle: true);

        (new PdoSessionKeepAlivePass())->process($container);

        $platform = $container->getDefinition(PdoSessionKeepAlivePass::PLATFORM_ALIVE_KEEPER_SERVICE_ID);
        $this->assertSame(PDOPlatformAliveKeeper::class, $platform->getClass());
        $this->assertEquals(
            [
                // Given once something has made it, and not made to be looked after.
                [self::PDO => new ServiceClosureArgument(
                    new Reference(self::PDO, ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE),
                )],
                [self::PDO => new Reference(self::ALIVE_KEEPER)],
            ],
            $platform->getArguments(),
        );

        // A handler of the resetter bundle's own, among its request cycle's initializers - beside the one it has
        // for Doctrine's connections, which is left as it is.
        $handler = $container->getDefinition(PdoSessionKeepAlivePass::CONNECTIONS_HANDLER_SERVICE_ID);
        $this->assertSame(ConnectionsHandler::class, $handler->getClass());
        $this->assertEquals(
            [[new Reference(PdoSessionKeepAlivePass::PLATFORM_ALIVE_KEEPER_SERVICE_ID)]],
            $handler->getArguments(),
        );
        $this->assertTrue($handler->hasTag('swoole_bundle_resetter.app_initializer'));
        $this->assertSame([], $container->getDefinition(ConnectionsHandler::class)->getArguments());
        $this->assertSame([], $container->getDefinition(self::PDO)->getTags());
    }

    public function testWithoutCoroutinesAndWithoutTheResetterBundleNothingLooksAfterIt(): void
    {
        $container = $this->containerWithAConnection();

        (new PdoSessionKeepAlivePass())->process($container);

        $this->assertFalse($container->hasDefinition(PdoSessionKeepAlivePass::CONNECTIONS_HANDLER_SERVICE_ID));
        $this->assertFalse($container->hasDefinition(PdoSessionKeepAlivePass::PLATFORM_ALIVE_KEEPER_SERVICE_ID));
    }

    public function testAConnectionMadeAnyOtherWayIsLeftAlone(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(ContainerConstants::PARAM_COROUTINES_ENABLED, true);
        $container->setDefinition('app.other.pdo', new Definition(PDO::class, ['sqlite::memory:']))
            ->addTag(ContainerConstants::TAG_STATEFUL_SERVICE);

        (new PdoSessionKeepAlivePass())->process($container);

        $this->assertSame(
            [[]],
            $container->getDefinition('app.other.pdo')->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
        $this->assertSame(['service_container', 'app.other.pdo'], array_keys($container->getDefinitions()));
    }

    private function containerWithAConnection(
        bool $coroutines = false,
        bool $pooled = false,
        bool $resetterBundle = false,
    ): ContainerBuilder {
        $container = new ContainerBuilder();
        $container->setParameter(ContainerConstants::PARAM_COROUTINES_ENABLED, $coroutines);

        $connection = (new Definition(PDO::class))
            ->setFactory([PdoConnectionFactory::class, 'newInstanceFromDsnOrUrl'])
            ->setArgument('$dsnOrUrl', 'sqlite:///:memory:');
        if ($pooled) {
            $connection->addTag(ContainerConstants::TAG_STATEFUL_SERVICE, ['limit' => 5]);
        }
        $container->setDefinition(self::PDO, $connection);

        if ($resetterBundle) {
            $container->setDefinition(ConnectionsHandler::class, new Definition(ConnectionsHandler::class));
        }

        return $container;
    }
}
