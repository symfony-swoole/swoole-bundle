<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use K911\Swoole\Bridge\Symfony\Container\Resetter;
use PixelFederation\DoctrineResettableEmBundle\DBAL\Connection\AliveKeeper;

final class ConnectionKeepAliveResetter implements Resetter
{
    private AliveKeeper $aliveKeeper;

    private string $connectionName;

    public function __construct(AliveKeeper $aliveKeeper, string $connectionName)
    {
        $this->aliveKeeper = $aliveKeeper;
        $this->connectionName = $connectionName;
    }

    public function reset(object $service): void
    {
        if (!$service instanceof Connection) {
            throw new \UnexpectedValueException(\sprintf('Unexpected class instance: %s ', \get_class($service)));
        }

        $this->aliveKeeper->keepAlive($service, $this->connectionName);
    }
}
