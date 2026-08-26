<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Messenger;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\ServiceProxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServicesPass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\MessengerProcessor;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Messenger\FinalTransportFactory;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Messenger\ReadOnlyTransportFactory;
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
 * All of it is compile-time - which factory builds what, and whether that can be stood in for - so
 * this is a unit test rather than a feature one. MessengerTaskWorkerGroupTest is what proves the
 * pooling works.
 */
#[CoversClass(MessengerProcessor::class)]
final class MessengerProcessorTest extends TestCase
{
    private const string FACTORY_ID = 'messenger.transport.doctrine.factory';

    private const string TRANSPORT_ID = 'messenger.transport.default';

    public function testAFactoryIsTaggedWithTheTransportItBuilds(): void
    {
        $container = $this->newContainer(DoctrineTransportFactory::class);

        $this->process($container);

        self::assertSame(
            [[
                'factoryMethod' => 'createTransport',
                'returnType' => DoctrineTransport::class,
            ]],
            $container->getDefinition(self::FACTORY_ID)->getTag(ContainerConstants::TAG_UNMANAGED_FACTORY),
        );
    }

    /**
     * The reason the transport class has to be named at all: the pool proxy extends it, and what these
     * factories declare returning is TransportInterface. Generated from that, the proxy would answer no
     * to every instanceof messenger discovers a transport's capabilities with - and
     * `messenger:setup-transports` would skip the transport without a word.
     */
    public function testTheTaggedClassCarriesTheCapabilityInterfaces(): void
    {
        $container = $this->newContainer(DoctrineTransportFactory::class);

        $this->process($container);

        /** @var array{0: array{returnType: class-string}} $tags */
        $tags = $container->getDefinition(self::FACTORY_ID)->getTag(ContainerConstants::TAG_UNMANAGED_FACTORY);

        self::assertContains(SetupableTransportInterface::class, class_implements($tags[0]['returnType']));
    }

    /**
     * The transports themselves are none of this processor's business, and it is worth pinning that
     * they stay untouched: naming a concrete class on a transport definition is what an earlier version
     * of this did, and what it named was wrong wherever `messenger.transport_factory` had been
     * decorated - the tagged factories go on answering the DSN while something else does the building.
     */
    public function testTheTransportDefinitionsAreLeftAlone(): void
    {
        $container = $this->newContainer(DoctrineTransportFactory::class);

        $this->process($container);

        $transport = $container->getDefinition(self::TRANSPORT_ID);

        self::assertSame(TransportInterface::class, $transport->getClass());
        self::assertSame([], $transport->getTag(ContainerConstants::TAG_STATEFUL_SERVICE));
    }

    public function testTheSyncTransportFactoryIsLeftShared(): void
    {
        $container = $this->newContainer(SyncTransportFactory::class);

        $this->process($container);

        $this->assertLeftShared($container);
    }

    public function testTheInMemoryTransportFactoryIsLeftShared(): void
    {
        $container = $this->newContainer(InMemoryTransportFactory::class);

        $this->process($container);

        $this->assertLeftShared($container);
    }

    /**
     * An application's own factory, which the naming convention has no reason to fit.
     */
    public function testAFactoryWhoseNameDoesNotSayWhatItBuildsIsLeftShared(): void
    {
        $container = $this->newContainer(UnconventionalTransportFactory::class);

        $this->process($container);

        $this->assertLeftShared($container);
    }

    /**
     * Skipped rather than failing the compile: there would be nothing for the proxy to extend, and a
     * transport that stays shared is a great deal better than a container that will not build.
     */
    public function testAFactoryBuildingAFinalTransportIsLeftShared(): void
    {
        $container = $this->newContainer(FinalTransportFactory::class);

        $this->process($container);

        $this->assertLeftShared($container);
    }

    public function testAReadOnlyFactoryIsLeftShared(): void
    {
        $container = $this->newContainer(ReadOnlyTransportFactory::class);

        $this->process($container);

        $this->assertLeftShared($container);
    }

    public function testItSaysWhenAFactoryDoesNotSayWhatItBuilds(): void
    {
        $container = $this->newContainer(UnconventionalTransportFactory::class);

        $this->process($container);

        self::assertSaidWhyItIsShared($container, 'UnconventionalTransport');
    }

    public function testItSaysWhenTheTransportCannotBeExtended(): void
    {
        $container = $this->newContainer(FinalTransportFactory::class);

        $this->process($container);

        self::assertSaidWhyItIsShared($container, 'cannot be extended');
    }

    public function testItSaysWhenTheFactoryItselfCannotBeWrapped(): void
    {
        $container = $this->newContainer(ReadOnlyTransportFactory::class);

        $this->process($container);

        self::assertSaidWhyItIsShared($container, 'read-only');
    }

    /**
     * Left shared on purpose is not a finding, and a line about it would sit in front of the ones that
     * are.
     */
    public function testItSaysNothingAboutAFactoryThatIsSharedOnPurpose(): void
    {
        $container = $this->newContainer(SyncTransportFactory::class);

        $this->process($container);

        self::assertSaidNothing($container);
    }

    public function testItSaysNothingAboutAFactoryItPooled(): void
    {
        $container = $this->newContainer(DoctrineTransportFactory::class);

        $this->process($container);

        self::assertSaidNothing($container);
    }

    private function assertLeftShared(ContainerBuilder $container): void
    {
        self::assertSame(
            [],
            $container->getDefinition(self::FACTORY_ID)->getTag(ContainerConstants::TAG_UNMANAGED_FACTORY),
        );
    }

    /**
     * @param non-empty-string $reason what the line has to say, beyond naming the factory
     */
    private static function assertSaidWhyItIsShared(ContainerBuilder $container, string $reason): void
    {
        $lines = self::poolingLog($container);

        self::assertCount(1, $lines, 'Exactly one line, so that a build log names each factory once.');
        self::assertStringContainsString(self::FACTORY_ID, $lines[0]);
        self::assertStringContainsString($reason, $lines[0]);
    }

    private static function assertSaidNothing(ContainerBuilder $container): void
    {
        self::assertSame([], self::poolingLog($container));
    }

    /**
     * @return list<string>
     */
    private static function poolingLog(ContainerBuilder $container): array
    {
        return array_values(array_filter(
            $container->getCompiler()->getLog(),
            static fn(string $line): bool => str_contains($line, StatefulServicesPass::class),
        ));
    }

    /**
     * A transport built by the factory under test, as FrameworkExtension would define it.
     *
     * @param class-string $factoryClass
     */
    private function newContainer(string $factoryClass): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $container->setDefinition(
            self::FACTORY_ID,
            (new Definition($factoryClass))->addTag('messenger.transport_factory'),
        );
        $container->setDefinition(
            self::TRANSPORT_ID,
            (new Definition(TransportInterface::class))
                ->setFactory([new Reference('messenger.transport_factory'), 'createTransport'])
                ->setArguments(['doctrine://default', ['transport_name' => 'default'], new Reference('serializer')])
                ->addTag('messenger.receiver', ['alias' => 'default']),
        );

        return $container;
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
