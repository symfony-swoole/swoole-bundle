<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Tideways\Apm;

use K911\Swoole\Server\Configurator\ConfiguratorInterface;
use Swoole\Http\Server;

final class WithApm implements ConfiguratorInterface
{
    public function __construct(private Apm $apm)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function configure(Server $server): void
    {
        $this->apm->instrument($server);
    }
}
