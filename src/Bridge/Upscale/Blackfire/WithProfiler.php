<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Upscale\Blackfire;

use K911\Swoole\Server\Configurator\ConfiguratorInterface;
use Swoole\Http\Server;

final class WithProfiler implements ConfiguratorInterface
{
    private ProfilerActivator $profilerActivator;

    public function __construct(ProfilerActivator $profilerActivator)
    {
        $this->profilerActivator = $profilerActivator;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(Server $server): void
    {
        $this->profilerActivator->activate($server);
    }
}
