<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Tideways\Apm;

use Closure;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Throwable;
use Tideways\Profiler;

final readonly class RequestProfiler
{
    private string $serviceName;

    public function __construct(
        private RequestDataProvider $dataProvider,
        string $serviceName = 'web',
    ) {
        $serviceName = trim($serviceName);
        $this->serviceName = $serviceName !== '' ? $serviceName : 'web';
    }

    public function profile(Closure $fn, Request $request, Response $response): void
    {
        $this->start($request);

        try {
            call_user_func($fn, $request, $response);
        } catch (Throwable $e) {
            Profiler::logException($e);

            throw $e;
        } finally {
            Profiler::stop();
        }
    }

    private function start(Request $swooleRequest): void
    {
        $request = $this->dataProvider->getSymfonyRequest($swooleRequest);
        $developerSession = $this->dataProvider->getDeveloperSession($request);
        $referenceId = $this->dataProvider->getReferenceId($request);

        Profiler::start(['service' => $this->serviceName, 'developer_session' => $developerSession]);
        Profiler::markAsWebTransaction();
        Profiler::setCustomVariable('http.host', $request->getHttpHost());
        Profiler::setCustomVariable('http.method', $request->getMethod());
        Profiler::setCustomVariable('http.url', $request->getPathInfo());

        if (!$referenceId) {
            return;
        }

        Profiler::setCustomVariable('tw.ref', $referenceId);
    }
}
