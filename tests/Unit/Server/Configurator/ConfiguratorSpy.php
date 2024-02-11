<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Configurator;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\Configurator\Configurator;

final class ConfiguratorSpy implements Configurator
{
    private bool $configured = false;

    public function configure(Server $server): void
    {
        $this->configured = true;
    }

    public function configured(): bool
    {
        return $this->configured;
    }
}
