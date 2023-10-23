<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Upscale\Blackfire\Monitoring;

use K911\Swoole\Server\Configurator\ConfiguratorInterface;
use Swoole\Http\Server;

final class WithApm implements ConfiguratorInterface
{
    private Apm $apm;

    public function __construct(Apm $apm)
    {
        $this->apm = $apm;
    }

    public function configure(Server $server): void
    {
        $this->apm->instrument($server);
    }
}
