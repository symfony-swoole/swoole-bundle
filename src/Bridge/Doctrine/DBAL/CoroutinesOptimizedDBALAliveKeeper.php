<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Doctrine\DBAL;

use Assert\Assertion;
use Doctrine\DBAL\Connection;
use Exception;
use Override;
use SwooleBundle\ResetterBundle\DBAL\Connection\DBALAliveKeeper;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use WeakMap;

/**
 * Coroutine-safe replacement for {@see \SwooleBundle\ResetterBundle\DBAL\Connection\OptimizedDBALAliveKeeper}.
 *
 * The original keeps the moment of the last ping in a plain `int` property. That is correct for a
 * single-threaded worker holding a single connection, but under coroutines it is wrong twice over:
 *
 * - the alive keeper is a single shared service while the connections it keeps alive are pooled, so
 *   one timestamp is shared by every connection in the pool: a ping on one connection suppresses the
 *   ping on all the others, and connections that are used rarely silently go stale;
 *
 * Both go away by keying the timestamps by connection instead. A WeakMap is used rather than an array
 * of `spl_object_id()`s because object ids are reused once an object is collected - a pooled connection
 * that gets dropped would hand its ping timestamp to whatever object takes over its id. The WeakMap
 * also drops its entry with the connection itself, so nothing has to be cleaned up by hand. It is never
 * re-assigned after the constructor, so there is no property write for fiber_viber to trip over, and its
 * own storage is internal to the engine rather than a tracked PHP property.
 */
final readonly class CoroutinesOptimizedDBALAliveKeeper implements DBALAliveKeeper
{
    private const int DEFAULT_PING_INTERVAL = 0;

    /**
     * @var WeakMap<Connection, int>
     */
    private WeakMap $lastPingedAt;

    public function __construct(
        private DBALAliveKeeper $decorated,
        private int $pingIntervalInSeconds = self::DEFAULT_PING_INTERVAL,
    ) {
        /** @var WeakMap<Connection, int> $lastPingedAt */
        $lastPingedAt = new WeakMap();
        $this->lastPingedAt = $lastPingedAt;
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function keepAlive(Connection $connection, string $connectionName): void
    {
        if (!$this->isPingNeeded($this->resolvePooledConnection($connection))) {
            return;
        }

        $this->decorated->keepAlive($connection, $connectionName);
    }

    /**
     * The connection reaches the keeper either straight from the service pool - as the instance being
     * assigned to a coroutine - or as the proxy standing in for the pool in the container. Only the
     * former identifies a physical connection, so the latter has to be unwrapped first; without that,
     * every coroutine would again be sharing the single timestamp of the single proxy object.
     */
    private function resolvePooledConnection(Connection $connection): Connection
    {
        if (!$connection instanceof ContextualProxy) {
            return $connection;
        }

        $contextualConnection = $connection->getContextualObject();
        Assertion::isInstanceOf($contextualConnection, Connection::class);

        return $contextualConnection;
    }

    private function isPingNeeded(Connection $connection): bool
    {
        $lastPingedAt = $this->lastPingedAt[$connection] ?? 0;
        $now = time();
        $this->lastPingedAt[$connection] = $now;

        return $now - $lastPingedAt >= $this->pingIntervalInSeconds;
    }
}
