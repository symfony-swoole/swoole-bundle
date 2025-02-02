<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Tideways\Apm;

use Closure;
use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Server\Middleware\Middleware;
use Tideways\Profiler;

final readonly class ProfilingMiddleware implements Middleware
{
    private Closure $nextMiddleware;

    public function __construct(
        callable $nextMiddleware,
        private RequestProfiler $profiler,
    ) {
        $this->nextMiddleware = Closure::fromCallable($nextMiddleware);
    }

    public function __invoke(Request $request, Response $response): void
    {
        if (!class_exists(Profiler::class) || php_sapi_name() !== 'cli') {
            // only run when Tideways is installed and the CLI sapi is used (that is how Swoole works)
            call_user_func($this->nextMiddleware, $request, $response);

            return;
        }

        $this->profiler->profile($this->nextMiddleware, $request, $response);
    }
}
