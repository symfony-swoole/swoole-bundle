<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Upscale\Blackfire\Profiling;

use Swoole\Http\Server;

interface ProfilerActivator
{
    public function activate(Server $server): void;
}
