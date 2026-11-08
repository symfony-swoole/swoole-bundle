<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Disposer;
use UnexpectedValueException;

/**
 * Closes a pooled connection its pool drops, so its session on the database server ends now rather than whenever the
 * object is collected - or, for an idle connection nobody closes, when the server's wait_timeout ends it.
 */
final readonly class ConnectionDisposer implements Disposer
{
    public function dispose(object $service): void
    {
        if (!$service instanceof Connection) {
            throw new UnexpectedValueException(
                sprintf('Invalid service - expected %s, got %s', Connection::class, $service::class)
            );
        }

        $service->close();
    }

    public static function getSupportedClass(): string
    {
        return Connection::class;
    }
}
