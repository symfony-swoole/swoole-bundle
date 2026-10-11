<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession;

use PDO;
use RuntimeException;
use SwooleBundle\ResetterBundle\Connection\PlatformAliveKeeper;

/**
 * Without coroutines: the PDO connections the resetter bundle looks after as a request starts, each with its alive
 * keeper - as its DBALPlatformAliveKeeper and RedisClusterPlatformAliveKeeper hold theirs. Given to a
 * ConnectionsHandler of the resetter bundle, which is one of its request cycle's initializers.
 */
final readonly class PDOPlatformAliveKeeper implements PlatformAliveKeeper
{
    /**
     * @param array<string, PDO> $connections
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

            $aliveKeeper->keepAlive($this->connections[$connectionName], $connectionName);
        }
    }
}
