<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\MessageHandler;

use Doctrine\DBAL\Connection;
use PDO;
use RuntimeException;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Message\LeaveConnectionInLostTransaction;
use UnexpectedValueException;

/**
 * Leaves the pooled connection exactly as a deadlock inside a nested transaction does, and fails.
 *
 * MySQL answers a deadlock by rolling the whole transaction back, savepoints included, and DBAL's rollback
 * to the savepoint then fails before its nesting level is lowered: the connection is left two levels deep in
 * a transaction the server no longer has. A deadlock is timing and cannot be asked for, so the server's half
 * is done here by hand - two levels opened through DBAL, and the transaction ended underneath it with a
 * ROLLBACK DBAL never sees.
 */
final readonly class LeaveConnectionInLostTransactionHandler
{
    public function __construct(private Connection $connection) {}

    public function __invoke(LeaveConnectionInLostTransaction $message): void
    {
        $this->connection->beginTransaction();
        $this->connection->beginTransaction();

        $native = $this->connection->getNativeConnection();

        if (!$native instanceof PDO) {
            throw new UnexpectedValueException(sprintf('Expected a PDO connection, got %s.', get_debug_type($native)));
        }

        $native->exec('ROLLBACK');

        throw new RuntimeException('The connection is left inside a transaction the server has rolled back.');
    }
}
