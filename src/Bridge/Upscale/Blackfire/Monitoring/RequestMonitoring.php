<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Upscale\Blackfire\Monitoring;

use K911\Swoole\Bridge\Symfony\HttpFoundation\RequestFactoryInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final class RequestMonitoring
{
    public function __construct(private readonly RequestFactoryInterface $requestFactory)
    {
    }

    public function monitor(\Closure $fn, Request $request, Response $response): void
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
        \BlackfireProbe::startTransaction($request->getPathInfo());
        \BlackfireProbe::setAttribute('http.target', $request->getPathInfo());
        \BlackfireProbe::setAttribute('http.url', $request->getRequestUri());
        \BlackfireProbe::setAttribute('http.method', $request->getMethod());
        \BlackfireProbe::setAttribute('http.host', $request->getHost());
        \BlackfireProbe::setAttribute('host', $request->getHost());
        \BlackfireProbe::setAttribute('framework', 'Symfony');
    }

    private function stop(): void
    {
        \BlackfireProbe::stopTransaction();
    }
}
