<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Tideways\Apm;

use Closure;
use K911\Swoole\Server\Middleware\Middleware;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Tideways\Profiler;

final class ProfilingMiddleware implements Middleware
{
    private Closure $nextMiddleware;

    private RequestProfiler $profiler;

    public function __construct(callable $nextMiddleware, RequestProfiler $profiler)
    {
        $this->nextMiddleware = Closure::fromCallable($nextMiddleware);
        $this->profiler = $profiler;
    }

    public function __invoke(Request $request, Response $response): void
    {
        if (!class_exists(Profiler::class) || 'cli' !== php_sapi_name()) {
            // only run when Tideways is installed and the CLI sapi is used (that is how Swoole works)
            call_user_func($this->nextMiddleware, $request, $response);

            return;
        }

        $this->profiler->profile($this->nextMiddleware, $request, $response);
    }
}
