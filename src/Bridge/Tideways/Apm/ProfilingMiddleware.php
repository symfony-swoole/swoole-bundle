<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Tideways\Apm;

use K911\Swoole\Server\Middleware\Middleware;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Tideways\Profiler;

final class ProfilingMiddleware implements Middleware
{
    private readonly \Closure $nextMiddleware;

    public function __construct(
        callable $nextMiddleware,
        private readonly RequestProfiler $profiler
    ) {
        $this->nextMiddleware = \Closure::fromCallable($nextMiddleware);
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
