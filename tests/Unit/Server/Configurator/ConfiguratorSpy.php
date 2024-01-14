<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\Configurator\ConfiguratorInterface;

final class ConfiguratorSpy implements ConfiguratorInterface
{
    public $configured = false;

    public function configure(Server $server): void
    {
        $this->configured = true;
    }
}
