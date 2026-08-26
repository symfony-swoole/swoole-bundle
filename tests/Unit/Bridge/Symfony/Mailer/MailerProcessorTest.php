<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Mailer;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\ServiceProxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServicesPass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Mailer\MailerProcessor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mailer\Transport\NativeTransportFactory;
use Symfony\Component\Mailer\Transport\NullTransportFactory;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\SendmailTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Which mail transport factories are pooled, as what, and what is said about the ones that are not.
 *
 * All of it is compile-time, so this is a unit test. That the pooling then does what it is for -
 * two coroutines sending over two connections rather than interleaving on one - is
 * MailerTransportPoolingTest.
 */
#[CoversClass(MailerProcessor::class)]
final class MailerProcessorTest extends TestCase
{
    private const string FACTORY_ID = 'mailer.transport_factory.smtp';

    private const string TRANSPORTS_ID = 'mailer.transports';

    public function testTheSmtpFactoryIsTaggedWithTheTransportItBuilds(): void
    {
        $container = $this->newContainer(EsmtpTransportFactory::class);

        $this->process($container);

        self::assertSame(
            [[
                'factoryMethod' => 'create',
                'returnType' => EsmtpTransport::class,
            ]],
            $container->getDefinition(self::FACTORY_ID)->getTag(ContainerConstants::TAG_UNMANAGED_FACTORY),
        );
    }

    /**
     * `create()`, not messenger's `createTransport()` - the same arrangement reached by two components
     * that named the method differently, which is exactly the sort of thing a shared rule gets wrong.
     */
    public function testItPoolsThroughTheMethodAMailerFactoryBuildsWith(): void
    {
        $container = $this->newContainer(SendmailTransportFactory::class);

        $this->process($container);

        /** @var array{0: array{factoryMethod: string, returnType: class-string}} $tags */
        $tags = $container->getDefinition(self::FACTORY_ID)->getTag(ContainerConstants::TAG_UNMANAGED_FACTORY);

        self::assertSame('create', $tags[0]['factoryMethod']);
        self::assertSame(SendmailTransport::class, $tags[0]['returnType']);
    }

    /**
     * Transports is final and is built once, so there is nothing here to pool and nothing to be gained
     * from trying - what it holds are the proxies, and they resolve per coroutine on every call.
     */
    public function testTheTransportsDefinitionIsLeftAlone(): void
    {
        $container = $this->newContainer(EsmtpTransportFactory::class);

        $this->process($container);

        $transports = $container->getDefinition(self::TRANSPORTS_ID);

        self::assertSame([], $transports->getTag(ContainerConstants::TAG_STATEFUL_SERVICE));
        self::assertSame([], $transports->getTag(ContainerConstants::TAG_UNMANAGED_FACTORY));
    }

    /**
     * A transport that accepts a message and drops it has nothing to share, and it is final besides -
     * so it is passed over on purpose rather than reported as something that could not be extended.
     */
    public function testTheNullTransportFactoryIsLeftSharedWithoutComment(): void
    {
        $container = $this->newContainer(NullTransportFactory::class);

        $this->process($container);

        $this->assertLeftShared($container);
        self::assertSaidNothing($container);
    }

    /**
     * The one Symfony ships that the convention cannot answer for: what it builds depends on php.ini's
     * sendmail_path, so there is no NativeTransport beside it to name.
     */
    public function testTheNativeTransportFactoryIsLeftSharedAndSaidSo(): void
    {
        $container = $this->newContainer(NativeTransportFactory::class);

        $this->process($container);

        $this->assertLeftShared($container);
        self::assertSaidWhyItIsShared($container, 'NativeTransport');
    }

    public function testItSaysNothingAboutAFactoryItPooled(): void
    {
        $container = $this->newContainer(EsmtpTransportFactory::class);

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
     * The factory under test and the Transports built out of it, as FrameworkExtension defines them.
     *
     * @param class-string $factoryClass
     */
    private function newContainer(string $factoryClass): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $container->setDefinition(
            self::FACTORY_ID,
            (new Definition($factoryClass))->addTag('mailer.transport_factory'),
        );
        $container->setDefinition(
            self::TRANSPORTS_ID,
            (new Definition(TransportInterface::class))
                ->setFactory([new Reference('mailer.transport_factory'), 'fromStrings'])
                ->setArguments([['main' => 'smtp://localhost:25']]),
        );

        return $container;
    }

    private function process(ContainerBuilder $container): void
    {
        (new MailerProcessor())->process($container, new class implements ServiceProxifier {
            #[Override]
            public function proxifyService(
                string $serviceId,
                ?string $externalResetter = null,
                int $resetPriority = 0,
            ): void {}
        });
    }
}
