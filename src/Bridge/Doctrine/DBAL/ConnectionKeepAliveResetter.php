<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use K911\Swoole\Bridge\Symfony\Container\Resetter;
use PixelFederation\DoctrineResettableEmBundle\DBAL\Connection\DBALAliveKeeper;

final class ConnectionKeepAliveResetter implements Resetter
{
    public function __construct(
        private readonly DBALAliveKeeper $aliveKeeper,
        private readonly string $connectionName
    ) {
    }

    public function reset(object $service): void
    {
        if (!$service instanceof Connection) {
            throw new \UnexpectedValueException(\sprintf('Unexpected class instance: %s ', $service::class));
        }

        $this->aliveKeeper->keepAlive($service, $this->connectionName);
    }
}
