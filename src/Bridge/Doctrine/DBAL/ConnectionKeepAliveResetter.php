<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use PixelFederation\DoctrineResettableEmBundle\DBAL\Connection\DBALAliveKeeper;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Resetter;
use UnexpectedValueException;

final class ConnectionKeepAliveResetter implements Resetter
{
    public function __construct(
        private readonly DBALAliveKeeper $aliveKeeper,
        private readonly string $connectionName,
    ) {}

    public function reset(object $service): void
    {
        if (!$service instanceof Connection) {
            throw new UnexpectedValueException(sprintf('Unexpected class instance: %s ', $service::class));
        }

        $this->aliveKeeper->keepAlive($service, $this->connectionName);
    }
}
