<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ProxyManager\Configuration as ProxyManagerConfiguration;
use ReflectionProperty;
use SwooleBundle\SwooleBundle\Bridge\Doctrine\DBAL\CoroutinesOptimizedDBALAliveKeeper;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generator;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\StaticServicePool;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Doctrine\RecordingDBALAliveKeeper;
use WeakMap;

#[CoversClass(CoroutinesOptimizedDBALAliveKeeper::class)]
final class CoroutinesOptimizedDBALAliveKeeperTest extends TestCase
{
    /**
     * Long enough that no second ping within a single test run can ever be due.
     */
    private const int LONG_INTERVAL = 3600;

    public function testTheFirstPingOfAConnectionAlwaysGoesThrough(): void
    {
        $decorated = new RecordingDBALAliveKeeper();
        $keeper = new CoroutinesOptimizedDBALAliveKeeper($decorated, self::LONG_INTERVAL);

        $keeper->keepAlive($this->newConnection(), 'default');

        self::assertSame(1, $decorated->pingCount());
    }

    public function testAFurtherPingOfTheSameConnectionWithinTheIntervalIsSkipped(): void
    {
        $decorated = new RecordingDBALAliveKeeper();
        $keeper = new CoroutinesOptimizedDBALAliveKeeper($decorated, self::LONG_INTERVAL);
        $connection = $this->newConnection();

        $keeper->keepAlive($connection, 'default');
        $keeper->keepAlive($connection, 'default');
        $keeper->keepAlive($connection, 'default');

        self::assertSame(1, $decorated->pingCount());
    }

    public function testAZeroIntervalPingsEveryTime(): void
    {
        $decorated = new RecordingDBALAliveKeeper();
        $keeper = new CoroutinesOptimizedDBALAliveKeeper($decorated);
        $connection = $this->newConnection();

        $keeper->keepAlive($connection, 'default');
        $keeper->keepAlive($connection, 'default');

        self::assertSame(2, $decorated->pingCount());
    }

    /**
     * The pooling regression: a single alive keeper serves every connection in the pool, so a single
     * timestamp would let the ping of one connection suppress the ping of all the others - leaving the
     * rarely used ones to go stale without ever being checked.
     */
    public function testEachConnectionKeepsItsOwnInterval(): void
    {
        $decorated = new RecordingDBALAliveKeeper();
        $keeper = new CoroutinesOptimizedDBALAliveKeeper($decorated, self::LONG_INTERVAL);
        $pooledConnections = [$this->newConnection(), $this->newConnection(), $this->newConnection()];

        foreach ($pooledConnections as $pooledConnection) {
            $keeper->keepAlive($pooledConnection, 'default');
        }

        // and none of them is due for a second ping
        foreach ($pooledConnections as $pooledConnection) {
            $keeper->keepAlive($pooledConnection, 'default');
        }

        self::assertSame(3, $decorated->pingCount());
        self::assertSame(
            $pooledConnections,
            array_map(
                static fn(array $ping): Connection => $ping['connection'],
                $decorated->pings(),
            ),
        );
    }

    /**
     * The proxy is a single shared object standing in for the whole pool, so keying by it would put
     * every pooled connection back under one timestamp - the very thing this keeper exists to avoid.
     */
    public function testAProxiedConnectionIsKeyedByTheInstanceItForwardsTo(): void
    {
        $decorated = new RecordingDBALAliveKeeper();
        $keeper = new CoroutinesOptimizedDBALAliveKeeper($decorated, self::LONG_INTERVAL);
        $firstProxy = $this->newProxiedConnection($this->newConnection());
        $secondProxy = $this->newProxiedConnection($this->newConnection());

        $keeper->keepAlive($firstProxy, 'default');
        $keeper->keepAlive($secondProxy, 'default');

        self::assertSame(2, $decorated->pingCount());

        $keeper->keepAlive($firstProxy, 'default');
        $keeper->keepAlive($secondProxy, 'default');

        self::assertSame(2, $decorated->pingCount());
    }

    /**
     * Two proxies over one and the same pooled instance are the same physical connection, so the
     * second of them must not be pinged again.
     */
    public function testTwoProxiesForwardingToTheSameInstanceShareItsInterval(): void
    {
        $decorated = new RecordingDBALAliveKeeper();
        $keeper = new CoroutinesOptimizedDBALAliveKeeper($decorated, self::LONG_INTERVAL);
        $connection = $this->newConnection();

        $keeper->keepAlive($this->newProxiedConnection($connection), 'default');
        $keeper->keepAlive($this->newProxiedConnection($connection), 'default');

        self::assertSame(1, $decorated->pingCount());
    }

    /**
     * Unwrapping decides which timestamp applies, nothing more: the decorated keeper has to be handed
     * the connection it was called with, so that a ping still goes through whatever the proxy adds.
     */
    public function testTheDecoratedKeeperIsHandedTheConnectionItWasCalledWith(): void
    {
        $decorated = new RecordingDBALAliveKeeper();
        $keeper = new CoroutinesOptimizedDBALAliveKeeper($decorated, self::LONG_INTERVAL);
        $proxy = $this->newProxiedConnection($this->newConnection());

        $keeper->keepAlive($proxy, 'connection_name');

        self::assertSame($proxy, $decorated->pings()[0]['connection']);
        self::assertSame('connection_name', $decorated->pings()[0]['connectionName']);
    }

    /**
     * Why a WeakMap and not an array of spl_object_id()s: object ids get reused once the object behind
     * them is gone, so a connection dropped from the pool would bequeath its ping timestamp to whatever
     * object takes over its id next.
     */
    public function testTheTimestampOfADiscardedConnectionIsDroppedWithIt(): void
    {
        $decorated = new RecordingDBALAliveKeeper();
        $keeper = new CoroutinesOptimizedDBALAliveKeeper($decorated, self::LONG_INTERVAL);
        $connection = $this->newConnection();

        $keeper->keepAlive($connection, 'default');

        self::assertCount(1, $this->timestampsOf($keeper));

        $decorated->forgetRecordedPings();
        unset($connection);

        self::assertCount(0, $this->timestampsOf($keeper));
    }

    /**
     * @return WeakMap<Connection, int>
     */
    private function timestampsOf(CoroutinesOptimizedDBALAliveKeeper $keeper): WeakMap
    {
        /** @var WeakMap<Connection, int> $timestamps */
        $timestamps = (new ReflectionProperty(CoroutinesOptimizedDBALAliveKeeper::class, 'lastPingedAt'))
            ->getValue($keeper);

        return $timestamps;
    }

    private function newConnection(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    /**
     * Builds the very proxy the container puts in place of a pooled connection, so that the unwrapping
     * is exercised against the generated getContextualObject() rather than a hand-written stand-in.
     */
    private function newProxiedConnection(Connection $connection): Connection
    {
        /** @var Connection&ContextualProxy<Connection> $proxy */
        $proxy = (new Generator(new ProxyManagerConfiguration()))
            ->createProxy(new StaticServicePool($connection), Connection::class);

        self::assertSame($connection, $proxy->getContextualObject());

        return $proxy;
    }
}
