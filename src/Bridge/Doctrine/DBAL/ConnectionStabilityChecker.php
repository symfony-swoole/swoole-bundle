<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\StabilityChecker;
use UnexpectedValueException;

/**
 * A pooled connection still inside a transaction between two units of work is broken, and is dropped.
 *
 * Every unit of work ends its transactions, so one still open at the boundary was left behind by a unit that
 * failed. The case that prompted this is a deadlock inside a nested transaction: MySQL rolls the whole
 * transaction back, savepoints included, DBAL's rollback to the savepoint then fails and its nesting level is
 * never lowered, and the connection keeps believing it is inside a transaction the server has forgotten. No
 * rollback can undo that - each one is refused the same way - and whatever uses the connection next fails on
 * it: in a messenger worker that is the retry of the failed message, sent on the same connection, and every
 * message after it.
 *
 * Dropped from the pool, the next get() builds a new connection. It is also closed here, so that its server
 * session ends now rather than whenever the object is collected.
 *
 * Reported to the error log rather than through the application's logger, for the reason
 * ServicePoolContainer::report() gives: a stability check runs inside the pool's release loop, where the
 * pooled logger may already have been handed back.
 */
final readonly class ConnectionStabilityChecker implements StabilityChecker
{
    public function isStable(object $service): bool
    {
        if (!$service instanceof Connection) {
            throw new UnexpectedValueException(
                sprintf('Invalid service - expected %s, got %s', Connection::class, $service::class)
            );
        }

        if (!$service->isTransactionActive()) {
            return true;
        }

        error_log(sprintf(
            'A pooled database connection was still inside a transaction (nesting level %d) between units of '
                . 'work, so it was closed and dropped; the next use gets a new one.',
            $service->getTransactionNestingLevel(),
        ));
        $service->close();

        return false;
    }

    public static function getSupportedClass(): string
    {
        return Connection::class;
    }
}
