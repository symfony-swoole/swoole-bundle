<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Override;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine\Channel;
use SwooleBundle\ResetterBundle\DBAL\Connection\DBALAliveKeeper;
use SwooleBundle\ResetterBundle\DBAL\Connection\OptimizedDBALAliveKeeper;
use SwooleBundle\ResetterBundle\DBAL\Connection\PingingDBALAliveKeeper;
use SwooleBundle\SwooleBundle\Bridge\Doctrine\DBAL\CoroutinesOptimizedDBALAliveKeeper;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Coroutine\CoroutinePool;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Doctrine\RecordingDBALAliveKeeper;
use SwooleBundle\SwooleBundle\Tests\Helper\SwooleFactoryFactory;

/**
 * Reproduces the bug CoroutinesOptimizedDBALAliveKeeper fixes.
 *
 * The alive keeper is a single shared service, while the connections it keeps alive are pooled and
 * handed out per coroutine. The resetter bundle's OptimizedDBALAliveKeeper remembers the moment of the
 * last ping in a plain int property, so that one timestamp ends up covering the whole pool: the ping of
 * whichever connection went first suppresses the ping of every other one, which is then never checked
 * and silently goes stale.
 *
 * That is observable without any instrumentation - the suppressed connection is simply never queried,
 * and never even connects - so coroutines are all this test needs to run. The other half of the bug,
 * the one shared property being written from two coroutines at once, is not asserted here.
 *
 * Both coroutines are kept alive at the same time on purpose, so the two pings really do come from two
 * concurrently live coroutines rather than one after the other from the same one.
 */
final class CoroutinesOptimizedDBALAliveKeeperTest extends TestCase
{
    private const float CHANNEL_TIMEOUT = 5.0;

    /**
     * Long enough that a second ping is never due for a connection already pinged in this test run -
     * so a ping getting through can only mean the keeper told the two connections apart.
     */
    private const int LONG_INTERVAL = 3600;

    private ?Swoole $swoole = null;

    #[Override]
    protected function setUp(): void
    {
        if (!extension_loaded('openswoole') && !extension_loaded('swoole')) {
            self::markTestSkipped('This test requires OpenSwoole or Swoole to run the pings as coroutines.');
        }

        // the pings below are real queries over a real MySQL connection, and pdo_mysql only yields to
        // other coroutines once the runtime is hooked - which is what the server does for its workers
        // (hook_flags = SWOOLE_HOOK_ALL) but nothing does for a plain PHPUnit process.
        $this->swoole = SwooleFactoryFactory::newInstance();
        $this->swoole->enableCoroutines();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->swoole?->disableCoroutines();
        $this->swoole = null;

        parent::tearDown();
    }

    public function testTheResetterBundleKeeperSuppressesThePingOfEveryFurtherConnection(): void
    {
        $decorated = new RecordingDBALAliveKeeper(new PingingDBALAliveKeeper());

        ['connections' => $connections, 'connected' => $connected] = $this->pingFromTwoCoroutines(
            new OptimizedDBALAliveKeeper($decorated, self::LONG_INTERVAL),
        );

        self::assertSame(
            [$connections[0]],
            $this->pingedConnections($decorated),
            'The single shared timestamp must be shown to suppress the second connection - if both were '
                . 'pinged here, this test no longer reproduces anything.'
        );

        self::assertTrue($connected[0]);
        self::assertFalse(
            $connected[1],
            'The suppressed connection is never queried at all, so it never even connects.'
        );
    }

    public function testTheCoroutineSafeKeeperPingsEveryPooledConnectionOnItsOwnInterval(): void
    {
        $decorated = new RecordingDBALAliveKeeper(new PingingDBALAliveKeeper());

        ['connections' => $connections, 'connected' => $connected] = $this->pingFromTwoCoroutines(
            new CoroutinesOptimizedDBALAliveKeeper($decorated, self::LONG_INTERVAL),
        );

        self::assertSame(
            $connections,
            $this->pingedConnections($decorated),
            'Both pooled connections must be pinged: neither may be suppressed by the other one\'s timestamp.'
        );

        foreach ($connected as $index => $wasConnected) {
            self::assertTrue(
                $wasConnected,
                sprintf('Connection #%d was never reached by a real query.', $index),
            );
        }
    }

    /**
     * The connections the keeper actually let through, straight from the recorder - checked against
     * Connection::isConnected() by the tests, which sees the same thing from the database side.
     *
     * @return list<Connection>
     */
    private function pingedConnections(RecordingDBALAliveKeeper $decorated): array
    {
        return array_map(
            static fn(array $ping): Connection => $ping['connection'],
            $decorated->pings(),
        );
    }

    /**
     * Pings one pooled connection per coroutine through the one shared keeper, the second coroutine
     * going ahead only once the first one has pinged and is waiting.
     *
     * Each coroutine reads whether its connection ended up connected and then closes it, both before it
     * ends. With the runtime hooked, the driver connection underneath is a coroutine-only object: it has
     * to be released from inside a coroutine, or it is destroyed with the caller's locals once this
     * method returns and takes the process down with "API must be called in the coroutine". Reading the
     * flag here rather than afterwards is what closing costs - Connection::isConnected() would report
     * false for both of them by then.
     *
     * @return array{connections: array{Connection, Connection}, connected: array{bool, bool}}
     */
    private function pingFromTwoCoroutines(DBALAliveKeeper $keeper): array
    {
        $connections = [$this->newConnection(), $this->newConnection()];
        $pinged = new Channel(1);
        $finished = new Channel(1);

        $firstUser = static function () use ($keeper, $connections, $pinged, $finished): array {
            $keeper->keepAlive($connections[0], 'default');
            $wasConnected = $connections[0]->isConnected();

            $pinged->push(true);
            $finished->pop(self::CHANNEL_TIMEOUT);
            $connections[0]->close();

            return ['first' => $wasConnected];
        };

        $secondUser = static function () use ($keeper, $connections, $pinged, $finished): array {
            $pinged->pop(self::CHANNEL_TIMEOUT);

            $keeper->keepAlive($connections[1], 'default');
            $wasConnected = $connections[1]->isConnected();

            $finished->push(true);
            $connections[1]->close();

            return ['second' => $wasConnected];
        };

        // anything thrown inside a coroutine is re-thrown here, so an unexpected failure still surfaces
        $results = array_merge(...CoroutinePool::fromCoroutines($firstUser, $secondUser)->run());

        return ['connections' => $connections, 'connected' => [$results['first'], $results['second']]];
    }

    /**
     * A real MySQL connection, on the same database the fixture application uses. An in-memory SQLite
     * connection would do no I/O at all, so the ping could never yield to another coroutine - the very
     * thing that puts two of them inside the shared keeper in the first place.
     *
     * Deliberately left unconnected: with the runtime hooked, PDO's constructor is a coroutine-only API
     * ("API must be called in the coroutine"), so the connecting has to happen where the pings do. DBAL
     * connects lazily on the first query, which is exactly there.
     */
    private function newConnection(): Connection
    {
        $host = getenv('DATABASE_HOST');

        return DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'charset' => 'utf8',
            'host' => is_string($host) && $host !== '' ? $host : 'db',
            'port' => 3306,
            'dbname' => 'db',
            'user' => 'user',
            'password' => 'pass',
        ]);
    }
}
