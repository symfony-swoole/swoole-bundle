<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession;

use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use SensitiveParameter;

/**
 * Asks the server whether it still answers, and connects again where it does not.
 *
 * A worker keeps the session handler's connection for as long as it lives; a database does not keep an idle one
 * that long. MySQL closes a connection after its `wait_timeout` - eight hours by default, a quiet night - and a
 * restart of the database closes them all.
 *
 * PDO has no way to connect again, so the connection is constructed again in place, with what it was constructed
 * with: the object stays the one its session handler holds. As PingingRedisClusterAliveKeeper does for a cluster.
 * Not inside a transaction, where a new connection would be a different one.
 */
final readonly class PingingPDOAliveKeeper implements PDOAliveKeeper
{
    /**
     * @param array{0: string, 1?: string|null, 2?: string|null, 3?: array<int, mixed>|null} $constructorArguments
     *     what the connection was constructed with (PdoConnectionFactory::constructorArgumentsFromDsnOrUrl())
     */
    public function __construct(
        #[SensitiveParameter]
        private array $constructorArguments,
        private ?LoggerInterface $logger = null,
    ) {}

    public function keepAlive(PDO $connection, string $connectionName): void
    {
        if ($connection->inTransaction()) {
            return;
        }

        try {
            $connection->query('SELECT 1');
        } catch (PDOException $e) {
            $this->logger?->info(
                sprintf("Exceptional reconnect for PDO connection '%s'", $connectionName),
                ['exception' => $e],
            );
            $connection->__construct(...$this->constructorArguments);
        }
    }
}
