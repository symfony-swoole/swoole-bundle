<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use SwooleBundle\ResetterBundle\DBAL\Connection\DBALAliveKeeper;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Initializer;
use UnexpectedValueException;

final readonly class ConnectionKeepAliveInitializer implements Initializer
{
    public function __construct(
        private DBALAliveKeeper $aliveKeeper,
        private string $connectionName,
    ) {}

    public function initialize(object $service): void
    {
        if (!$service instanceof Connection) {
            throw new UnexpectedValueException(sprintf('Unexpected class instance: %s ', $service::class));
        }

        $this->aliveKeeper->keepAlive($service, $this->connectionName);
    }
}
