<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\Configurator\Configurator;

final class ConfiguratorDummy implements Configurator
{
    public function configure(Server $server): void
    {
        // noop
    }
}
