<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use SwooleBundle\ResetterBundle\Connection\ConnectionsHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession\PdoConnectionFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession\PDOKeepAliveInitializer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession\PDOPlatformAliveKeeper;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession\PingingPDOAliveKeeper;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Keeps the PDO connections made by PdoConnectionFactory alive - the session handler's, which a worker holds for
 * longer than a database keeps an idle connection.
 *
 * Each gets an alive keeper that knows what it was constructed with (PingingPDOAliveKeeper), and is looked after
 * the way Doctrine's connections are:
 *  - with coroutines, as a pooled connection is handed to a coroutine: an initializer on its stateful service
 *    (PDOKeepAliveInitializer), unless the service names one of its own;
 *  - without them, as a request starts, by the resetter bundle: a ConnectionsHandler of its own for them, among its
 *    request cycle's initializers. Without the resetter bundle nothing looks after a connection, as nothing does
 *    after Doctrine's.
 */
final class PdoSessionKeepAlivePass implements CompilerPassInterface
{
    public const string ALIVE_KEEPER_SERVICE_ID = 'swoole_bundle.session.pdo.alive_keeper.%s';
    public const string INITIALIZER_SERVICE_ID = 'swoole_bundle.session.pdo.keep_alive_initializer.%s';
    public const string PLATFORM_ALIVE_KEEPER_SERVICE_ID = 'swoole_bundle.session.pdo.platform_alive_keeper';
    public const string CONNECTIONS_HANDLER_SERVICE_ID = 'swoole_bundle.session.pdo.connections_handler';

    private const string RESETTER_INITIALIZER_TAG = 'swoole_bundle_resetter.app_initializer';

    public function process(ContainerBuilder $container): void
    {
        $coroutines = $container->hasParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)
            && (bool) $container->getParameter(ContainerConstants::PARAM_COROUTINES_ENABLED);

        $connections = [];
        $aliveKeepers = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            $dsnOrUrl = self::dsnOrUrlOf($definition);
            if ($dsnOrUrl === null) {
                continue;
            }

            $aliveKeeperId = sprintf(self::ALIVE_KEEPER_SERVICE_ID, $id);
            $container->setDefinition($aliveKeeperId, new Definition(PingingPDOAliveKeeper::class, [
                (new Definition('array', [$dsnOrUrl]))
                    ->setFactory([PdoConnectionFactory::class, 'constructorArgumentsFromDsnOrUrl']),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ]));

            if ($coroutines) {
                $this->initializeAsHandedOut($container, $id, $definition, $aliveKeeperId);

                continue;
            }

            $connections[$id] = new Reference($id);
            $aliveKeepers[$id] = new Reference($aliveKeeperId);
        }

        if ($connections === [] || !$container->hasDefinition(ConnectionsHandler::class)) {
            return;
        }

        $container->setDefinition(
            self::PLATFORM_ALIVE_KEEPER_SERVICE_ID,
            new Definition(PDOPlatformAliveKeeper::class, [$connections, $aliveKeepers]),
        );
        $container->setDefinition(
            self::CONNECTIONS_HANDLER_SERVICE_ID,
            (new Definition(ConnectionsHandler::class, [[new Reference(self::PLATFORM_ALIVE_KEEPER_SERVICE_ID)]]))
                ->addTag(self::RESETTER_INITIALIZER_TAG),
        );
    }

    /**
     * What the factory makes the service's connection from; null for a service it does not make.
     */
    private static function dsnOrUrlOf(Definition $definition): mixed
    {
        $factory = $definition->getFactory();
        if (
            !is_array($factory)
            || $factory[0] !== PdoConnectionFactory::class
            || $factory[1] !== 'newInstanceFromDsnOrUrl'
        ) {
            return null;
        }

        $arguments = $definition->getArguments();

        return $arguments['$dsnOrUrl'] ?? $arguments[0] ?? null;
    }

    private function initializeAsHandedOut(
        ContainerBuilder $container,
        string $id,
        Definition $definition,
        string $aliveKeeperId,
    ): void {
        $tags = $definition->getTag(ContainerConstants::TAG_STATEFUL_SERVICE);
        // Not pooled: one connection shared by every coroutine, which is the application's to look after. And one
        // that names an initializer of its own is looked after by that.
        if ($tags === [] || isset($tags[0]['initializer'])) {
            return;
        }

        $initializerId = sprintf(self::INITIALIZER_SERVICE_ID, $id);
        $container->setDefinition(
            $initializerId,
            new Definition(PDOKeepAliveInitializer::class, [new Reference($aliveKeeperId), $id]),
        );

        $tags[0]['initializer'] = $initializerId;
        $definition->clearTag(ContainerConstants::TAG_STATEFUL_SERVICE);
        foreach ($tags as $tag) {
            $definition->addTag(ContainerConstants::TAG_STATEFUL_SERVICE, $tag);
        }
    }
}
