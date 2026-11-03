<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Doctrine\DBAL;

use Assert\Assertion;
use Doctrine\DBAL\Connection;
use Exception;
use Override;
use SwooleBundle\ResetterBundle\DBAL\Connection\DBALAliveKeeper;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use WeakReference;

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
 * Both go away by keying the timestamps by connection instead.
 *
 * Keyed by `spl_object_id()`, with a weak reference to the connection beside each timestamp. An object
 * id is reused once its object is collected, so the id alone would let a pooled connection that gets
 * dropped hand its timestamp to whatever object takes over the id; the weak reference tells the two
 * apart, and an entry whose connection is gone is dropped the next time a connection is seen for the
 * first time.
 *
 * Not a WeakMap, which would do all of that by itself, because the timestamps are shared by every
 * coroutine of the worker by design, and a checker of cross-coroutine writes such as fiber_viber has to be
 * told so. fiber_viber records a WeakMap's ownership for the map as a whole, wherever the map is held, so
 * every coroutine after the first to ping is reported, and excluding the property holding the map does not
 * reach it - only excluding every WeakMap of the worker would. An array is the property's own, so excluding
 * the property is enough. Nothing between reading a timestamp and writing it can suspend a coroutine.
 */
final class CoroutinesOptimizedDBALAliveKeeper implements DBALAliveKeeper
{
    private const int DEFAULT_PING_INTERVAL = 0;

    /**
     * @var array<int, array{WeakReference<Connection>, int}>
     */
    private array $lastPingedAt = [];

    public function __construct(
        private readonly DBALAliveKeeper $decorated,
        private readonly int $pingIntervalInSeconds = self::DEFAULT_PING_INTERVAL,
    ) {}

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
        $id = spl_object_id($connection);
        $lastPingedAt = 0;

        if (isset($this->lastPingedAt[$id]) && $this->lastPingedAt[$id][0]->get() === $connection) {
            $lastPingedAt = $this->lastPingedAt[$id][1];
        } else {
            $this->forgetCollectedConnections();
        }

        $now = time();
        $this->lastPingedAt[$id] = [WeakReference::create($connection), $now];

        return $now - $lastPingedAt >= $this->pingIntervalInSeconds;
    }

    private function forgetCollectedConnections(): void
    {
        foreach ($this->lastPingedAt as $id => [$connection]) {
            if ($connection->get() !== null) {
                continue;
            }

            unset($this->lastPingedAt[$id]);
        }
    }
}
