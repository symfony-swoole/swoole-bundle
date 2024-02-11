<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Tideways\Apm;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\Configurator\Configurator;

final class WithApm implements Configurator
{
    public function __construct(private readonly Apm $apm) {}

    public function configure(Server $server): void
    {
        $this->apm->instrument($server);
    }
}
