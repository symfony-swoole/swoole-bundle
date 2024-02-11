<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Upscale\Blackfire\Profiling;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\Configurator\Configurator;

final class WithProfiler implements Configurator
{
    public function __construct(private readonly ProfilerActivator $profilerActivator) {}

    public function configure(Server $server): void
    {
        $this->profilerActivator->activate($server);
    }
}
