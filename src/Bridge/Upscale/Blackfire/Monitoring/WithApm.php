<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Upscale\Blackfire\Monitoring;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\Configurator\Configurator;

final readonly class WithApm implements Configurator
{
    public function __construct(private Apm $apm) {}

    public function configure(Server $server): void
    {
        $this->apm->instrument($server);
    }
}
