<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Upscale\Blackfire\Monitoring;

use BlackfireProbe;
use Closure;
use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Server\Middleware\Middleware;

final class MonitoringMiddleware implements Middleware
{
    private readonly Closure $nextMiddleware;

    public function __construct(callable $nextMiddleware, private readonly RequestMonitoring $monitoring)
    {
        $this->nextMiddleware = Closure::fromCallable($nextMiddleware);
    }

    public function __invoke(Request $request, Response $response): void
    {
        if (!class_exists(BlackfireProbe::class) || php_sapi_name() !== 'cli') {
            // only run when Blackfire is installed and the CLI sapi is used (that is how Swoole works)
            call_user_func($this->nextMiddleware, $request, $response);

            return;
        }

        $this->monitoring->monitor($this->nextMiddleware, $request, $response);
    }
}
