<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Messenger;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\ServiceProxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\MessengerProcessor;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Messenger\FinalTransportFactory;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Messenger\UnconventionalTransportFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransportFactory;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransportFactory;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransportFactory;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * The half of the processor that gives each coroutine a transport of its own.
 *
 * All of it is compile-time: which factory builds a DSN, what that factory's transport class is, and
 * whether it is one a proxy can be generated from. Nothing is instantiated, so this is a unit test
 * rather than a feature one - MessengerTaskWorkerGroupTest is what proves the pooling works.
 */
#[CoversClass(MessengerProcessor::class)]
final class MessengerProcessorTest extends TestCase
{
    private const string TRANSPORT_ID = 'messenger.transport.default';

    public function testATransportIsGivenItsConcreteClassAndPooled(): void
    {
        $container = $this->newContainer('doctrine://default?queue_name=default');

        $this->process($container);

        $definition = $container->getDefinition(self::TRANSPORT_ID);

        self::assertSame(DoctrineTransport::class, $definition->getClass());
        self::assertSame([[]], $definition->getTag(ContainerConstants::TAG_STATEFUL_SERVICE));
    }

    /**
     * The reason the class has to be written at all: a proxy generated from TransportInterface answers
     * no to every instanceof messenger discovers a transport's capabilities with.
     */
    public function testTheClassItIsGivenCarriesTheCapabilityInterfaces(): void
    {
        $container = $this->newContainer('doctrine://default?queue_name=default');

        $this->process($container);

        /** @var class-string $class */
        $class = $container->getDefinition(self::TRANSPORT_ID)->getClass();

        self::assertContains(SetupableTransportInterface::class, class_implements($class));
    }

    /**
     * A DSN out of the environment is a placeholder here, so which transport it will be is not
     * knowable - and resolving it would answer with the machine that compiled the container.
     */
    public function testATransportWhoseDsnComesFromTheEnvironmentIsLeftShared(): void
    {
        $container = new ContainerBuilder();
        $dsn = $container->getParameterBag()->resolveValue('%env(MESSENGER_TRANSPORT_DSN)%');
        self::assertIsString($dsn);

        $this->addTransport($container, $dsn);
        $this->addFactory($container, DoctrineTransportFactory::class);

        $this->process($container);

        $this->assertLeftShared($container);
    }

    public function testTheSyncTransportIsLeftShared(): void
    {
        $container = $this->newContainer('sync://', SyncTransportFactory::class);

        $this->process($container);

        $this->assertLeftShared($container);
    }

    public function testTheInMemoryTransportIsLeftShared(): void
    {
        $container = $this->newContainer('in-memory://', InMemoryTransportFactory::class);

        $this->process($container);

        $this->assertLeftShared($container);
    }

    /**
     * An application's own factory, which the naming convention has no reason to fit.
     */
    public function testATransportWhoseFactoryDoesNotFollowTheConventionIsLeftShared(): void
    {
        $container = $this->newContainer('unconventional://', UnconventionalTransportFactory::class);

        $this->process($container);

        $this->assertLeftShared($container);
    }

    /**
     * Skipped rather than failing the compile: there is nothing for the proxy to extend, and a
     * transport that stays shared is a great deal better than a container that will not build.
     */
    public function testAFinalTransportIsLeftShared(): void
    {
        $container = $this->newContainer('final://', FinalTransportFactory::class);

        $this->process($container);

        $this->assertLeftShared($container);
    }

    public function testATransportNoFactoryClaimsIsLeftShared(): void
    {
        $container = $this->newContainer('nobody-handles-this://');

        $this->process($container);

        $this->assertLeftShared($container);
    }

    /**
     * A definition that already names a class was written by somebody who knew what they meant, and is
     * not this processor's to rewrite.
     */
    public function testATransportThatAlreadyNamesItsClassIsLeftAlone(): void
    {
        $container = $this->newContainer('doctrine://default');
        $container->getDefinition(self::TRANSPORT_ID)->setClass(DoctrineTransport::class);

        $this->process($container);

        self::assertSame([], $container->getDefinition(self::TRANSPORT_ID)->getTag(
            ContainerConstants::TAG_STATEFUL_SERVICE,
        ));
    }

    private function assertLeftShared(ContainerBuilder $container): void
    {
        $definition = $container->getDefinition(self::TRANSPORT_ID);

        self::assertSame(TransportInterface::class, $definition->getClass());
        self::assertSame([], $definition->getTag(ContainerConstants::TAG_STATEFUL_SERVICE));
    }

    /**
     * @param class-string $factoryClass
     */
    private function newContainer(
        string $dsn,
        string $factoryClass = DoctrineTransportFactory::class,
    ): ContainerBuilder {
        $container = new ContainerBuilder();

        $this->addTransport($container, $dsn);
        $this->addFactory($container, $factoryClass);

        return $container;
    }

    private function addTransport(ContainerBuilder $container, string $dsn): void
    {
        $container->setDefinition(
            self::TRANSPORT_ID,
            (new Definition(TransportInterface::class))
                ->setFactory([new Reference('messenger.transport_factory'), 'createTransport'])
                ->setArguments([$dsn, ['transport_name' => 'default'], new Reference('messenger.default_serializer')])
                ->addTag('messenger.receiver', ['alias' => 'default']),
        );
    }

    /**
     * @param class-string $factoryClass
     */
    private function addFactory(ContainerBuilder $container, string $factoryClass): void
    {
        $container->setDefinition(
            'messenger.transport.' . $factoryClass . '.factory',
            (new Definition($factoryClass))->addTag('messenger.transport_factory'),
        );
    }

    private function process(ContainerBuilder $container): void
    {
        (new MessengerProcessor())->process($container, new class implements ServiceProxifier {
            #[Override]
            public function proxifyService(
                string $serviceId,
                ?string $externalResetter = null,
                int $resetPriority = 0,
            ): void {}
        });
    }
}
