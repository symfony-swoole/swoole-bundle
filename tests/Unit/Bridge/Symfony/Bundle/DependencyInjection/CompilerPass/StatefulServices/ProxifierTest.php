<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SwooleBundle\SwooleBundle\Bridge\Doctrine\DBAL\ConnectionDisposer;
use SwooleBundle\SwooleBundle\Bridge\Doctrine\DBAL\ConnectionStabilityChecker;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\{
    ClassModificationProcessor,
    Proxifier,
};
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Reflection\ClassModifier;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\LazyGhostExample;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(Proxifier::class)]
final class ProxifierTest extends TestCase
{
    private const string SERVICE_ID = 'some.pooled.service';

    /**
     * Pooling a service twice wraps the proxy in another proxy, and leaves the second pool entry
     * deciding how the service is reset - including deciding that it is not, when the second caller
     * knows nothing about resetting and passes null, because ServicePoolContainer skips a pool entry
     * without a resetter. The service then stops being reset with nothing to show for it, which is
     * what makes this worth refusing outright rather than quietly ignoring.
     */
    public function testProxifyingAServiceThatIsAlreadyPooledIsRefused(): void
    {
        $container = $this->newContainer();
        $container->register(self::SERVICE_ID, LazyGhostExample::class);
        // What proxifying leaves behind, and so what says this service has already been through it.
        $container->register(self::SERVICE_ID . '.swoole_coop.wrapped', LazyGhostExample::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/already proxified/');

        $this->newProxifier($container)->proxifyService(self::SERVICE_ID);
    }

    /**
     * The message has to name the service and say what to do about it, since the two calls responsible
     * are usually in different files and neither looks wrong on its own.
     */
    public function testTheRefusalNamesTheServiceAndTheTagThatMakesTheSecondCallRedundant(): void
    {
        $container = $this->newContainer();
        $container->register(self::SERVICE_ID, LazyGhostExample::class);
        $container->register(self::SERVICE_ID . '.swoole_coop.wrapped', LazyGhostExample::class);

        $this->expectExceptionMessageMatches(
            sprintf(
                '/%s.+%s/s',
                preg_quote(self::SERVICE_ID, '/'),
                preg_quote(ContainerConstants::TAG_STATEFUL_SERVICE, '/'),
            ),
        );

        $this->newProxifier($container)->proxifyService(self::SERVICE_ID);
    }

    /**
     * A missing service is a different mistake and keeps its own message.
     */
    public function testProxifyingAServiceThatDoesNotExistIsRefusedSeparately(): void
    {
        $container = $this->newContainer();

        $this->expectExceptionMessageMatches('/Service missing/');

        $this->newProxifier($container)->proxifyService(self::SERVICE_ID);
    }

    /**
     * The pool keeps as many instances as were ever in use at once unless something says otherwise.
     */
    public function testAPoolHasNoIdleLimitUnlessOneIsSet(): void
    {
        $container = $this->newContainer();
        $container->register(self::SERVICE_ID, LazyGhostExample::class);

        $this->proxify($container);

        self::assertNull($this->poolDefinitionArgument($container, 7));
    }

    public function testAPoolTakesTheGlobalIdleLimit(): void
    {
        $container = $this->newContainer();
        $container->setParameter(ContainerConstants::PARAM_COROUTINES_MAX_IDLE_SVC_INSTANCES, 3);
        $container->register(self::SERVICE_ID, LazyGhostExample::class);

        $this->proxify($container);

        self::assertSame(3, $this->poolDefinitionArgument($container, 7));
    }

    public function testAServicesOwnIdleLimitIsTakenOverTheGlobalOne(): void
    {
        $container = $this->newContainer();
        $container->setParameter(ContainerConstants::PARAM_COROUTINES_MAX_IDLE_SVC_INSTANCES, 3);
        $container->register(self::SERVICE_ID, LazyGhostExample::class)
            ->addTag(ContainerConstants::TAG_STATEFUL_SERVICE, ['idle_limit' => 0]);

        $this->proxify($container);

        self::assertSame(0, $this->poolDefinitionArgument($container, 7));
    }

    /**
     * A connection a pool drops is closed, so the database ends its session as soon as it is dropped.
     */
    public function testADbalConnectionsPoolDisposesOfItsConnectionsByClosingThem(): void
    {
        $container = $this->newContainer();
        $container->register(ConnectionStabilityChecker::class, ConnectionStabilityChecker::class);
        $container->register(ConnectionDisposer::class, ConnectionDisposer::class);
        $container->register(self::SERVICE_ID, Connection::class);

        $this->proxify($container);

        self::assertEquals(new Reference(ConnectionDisposer::class), $this->poolDefinitionArgument($container, 8));
    }

    public function testAPoolOfAClassNoDisposerSupportsHasNone(): void
    {
        $container = $this->newContainer();
        $container->register(self::SERVICE_ID, LazyGhostExample::class);

        $this->proxify($container);

        self::assertNull($this->poolDefinitionArgument($container, 8));
    }

    /**
     * Proxifying a service strips `final` from its class, which z-engine does.
     */
    private function proxify(ContainerBuilder $container): void
    {
        ClassModifier::initialize();
        $this->newProxifier($container)->proxifyService(self::SERVICE_ID);
    }

    private function poolDefinitionArgument(ContainerBuilder $container, int $index): mixed
    {
        return $container->getDefinition(self::SERVICE_ID . '.swoole_coop.service_pool')->getArgument($index);
    }

    private function newProxifier(ContainerBuilder $container): Proxifier
    {
        return new Proxifier($container, new ClassModificationProcessor($container));
    }

    private function newContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter(ContainerConstants::PARAM_COROUTINES_MAX_SVC_INSTANCES, 20);

        return $container;
    }
}
