<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Upscale\Blackfire\Monitoring;

use BlackfireProbe;
use Closure;
use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\RequestFactory;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final readonly class RequestMonitoring
{
    public function __construct(private RequestFactory $requestFactory) {}

    public function monitor(Closure $fn, Request $request, Response $response): void
    {
        $sfRequest = $this->requestFactory->make($request);

        try {
            $this->start($sfRequest);
            call_user_func($fn, $request, $response);
        } finally {
            $this->stop();
        }
    }

    private function start(SymfonyRequest $request): void
    {
        BlackfireProbe::startTransaction();
    }

    private function stop(): void
    {
        BlackfireProbe::stopTransaction();
    }
}
