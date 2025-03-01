<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Upscale\Blackfire\Monitoring;

use BlackfireProbe;
use Closure;
use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\RequestFactory;
use SwooleBundle\SwooleBundle\Common\System\System;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final class RequestMonitoring
{
    private string|null $blackfireVersion = null;

    public function __construct(
        private RequestFactory $requestFactory,
        private System $system,
    ) {}

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
        $blackfireVersion = $this->getBlackfireVersion();

        if ($blackfireVersion === '') {
            return;
        }

        $transactionName = $request->getMethod() . ' ' . $request->getPathInfo();

        if (version_compare($blackfireVersion, '1.78.0', '>=')) {
            BlackfireProbe::startTransaction($transactionName);
        } else {
            BlackfireProbe::startTransaction();
            BlackfireProbe::setTransactionName($transactionName);
        }

        if (!method_exists(BlackfireProbe::class, 'setAttribute')) {
            return;
        }

        BlackfireProbe::setAttribute('http.target', $request->getPathInfo());
        BlackfireProbe::setAttribute('http.url', $request->getRequestUri());
        BlackfireProbe::setAttribute('http.method', $request->getMethod());
        BlackfireProbe::setAttribute('http.host', $request->getHost());
        BlackfireProbe::setAttribute('host', $request->getHost());
        BlackfireProbe::setAttribute('framework', sprintf('Symfony with %s', $this->system->extension()->toString()));
    }

    private function stop(): void
    {
        if ($this->getBlackfireVersion() === '') {
            return;
        }

        BlackfireProbe::stopTransaction();
    }

    private function getBlackfireVersion(): string
    {
        if ($this->blackfireVersion === null) {
            $this->blackfireVersion = (($v = phpversion('blackfire')) === false ? '' : $v);
        }

        return $this->blackfireVersion;
    }
}
