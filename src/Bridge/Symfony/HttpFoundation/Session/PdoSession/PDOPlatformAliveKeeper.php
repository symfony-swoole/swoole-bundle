<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession;

use Closure;
use PDO;
use RuntimeException;
use SwooleBundle\ResetterBundle\Connection\PlatformAliveKeeper;

/**
 * Without coroutines: the PDO connections the resetter bundle looks after as a request starts, each with its alive
 * keeper - as its DBALPlatformAliveKeeper and RedisClusterPlatformAliveKeeper hold theirs. Those that are open: as
 * its PassiveIgnoringDBALAliveKeeper leaves alone a DBAL connection that is not, none is opened to be looked after.
 * Given to a ConnectionsHandler of the resetter bundle, which is one of its request cycle's initializers.
 */
final readonly class PDOPlatformAliveKeeper implements PlatformAliveKeeper
{
    /**
     * @param array<string, Closure(): ?PDO> $connections each gives its connection once something has made it, and
     *     null before: a connection nobody has asked for is not made to be looked after, or a request that needs no
     *     session would open one - and fail where the database is away
     * @param array<string, PDOAliveKeeper> $aliveKeepers
     */
    public function __construct(
        private array $connections,
        private array $aliveKeepers,
    ) {}

    public function keepAlive(): void
    {
        foreach ($this->aliveKeepers as $connectionName => $aliveKeeper) {
            if (!isset($this->connections[$connectionName])) {
                throw new RuntimeException(sprintf('Connection "%s" is missing.', $connectionName));
            }

            $connection = ($this->connections[$connectionName])();
            if ($connection === null) {
                continue;
            }

            $aliveKeeper->keepAlive($connection, $connectionName);
        }
    }
}
