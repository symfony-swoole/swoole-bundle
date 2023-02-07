<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Upscale\Blackfire\Monitoring;

use K911\Swoole\Bridge\Symfony\HttpFoundation\RequestFactoryInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final class RequestMonitoring
{
    private RequestFactoryInterface $requestFactory;

    public function __construct(RequestFactoryInterface $requestFactory)
    {
        $this->requestFactory = $requestFactory;
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
        \BlackfireProbe::setAttribute('http.target', $request->getPathInfo()); /* @phpstan-ignore-line */
        \BlackfireProbe::setAttribute('http.url', $request->getRequestUri()); /* @phpstan-ignore-line */
        \BlackfireProbe::setAttribute('http.method', $request->getMethod()); /* @phpstan-ignore-line */
        \BlackfireProbe::setAttribute('http.host', $request->getHost()); /* @phpstan-ignore-line */
        \BlackfireProbe::setAttribute('host', $request->getHost()); /* @phpstan-ignore-line */
        \BlackfireProbe::setAttribute('framework', 'Symfony'); /* @phpstan-ignore-line */
    }

    private function stop(): void
    {
        \BlackfireProbe::stopTransaction();
    }
}
