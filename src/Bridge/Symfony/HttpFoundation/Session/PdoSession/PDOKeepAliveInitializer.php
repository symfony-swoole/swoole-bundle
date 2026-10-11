<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession;

use PDO;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Initializer;
use UnexpectedValueException;

/**
 * With coroutines: a pooled PDO connection is looked after as it is handed to a coroutine, before the coroutine
 * uses it - as ConnectionKeepAliveInitializer does for a Doctrine connection.
 */
final readonly class PDOKeepAliveInitializer implements Initializer
{
    public function __construct(
        private PDOAliveKeeper $aliveKeeper,
        private string $connectionName,
    ) {}

    public function initialize(object $service): void
    {
        if (!$service instanceof PDO) {
            throw new UnexpectedValueException(sprintf('Unexpected class instance: %s ', $service::class));
        }

        $this->aliveKeeper->keepAlive($service, $this->connectionName);
    }
}
