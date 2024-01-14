<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Tideways\Apm;

use Swoole\Http\Request as SwooleRequest;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\RequestFactoryInterface;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final class RequestDataProvider
{
    public function __construct(private readonly RequestFactoryInterface $requestFactory)
    {
    }

    public function getSymfonyRequest(SwooleRequest $request): SymfonyRequest
    {
        return $this->requestFactory->make($request);
    }

    public function getDeveloperSession(SymfonyRequest $request): ?string
    {
        $developerSession = null;

        if ($request->query->has('_tideways')) {
            $developerSession = http_build_query((array) $request->query->get('_tideways'));
        } elseif ($request->headers->has('X-TIDEWAYS-PROFILER')) {
            $developerSession = $request->headers->get('X-TIDEWAYS-PROFILER');
        } elseif ($request->cookies->has('TIDEWAYS_SESSION')) {
            $developerSession = $request->cookies->get('TIDEWAYS_SESSION');
        }

        return is_string($developerSession) ? $developerSession : null;
    }

    public function getReferenceId(SymfonyRequest $request): ?string
    {
        $referenceId = $request->query->get('_tideways_ref', $request->headers->get('X-Tideways-Ref'));

        if ($request->cookies->has('TIDEWAYS_REF')) {
            $referenceId = $request->cookies->get('TIDEWAYS_REF');
        }

        return is_string($referenceId) ? $referenceId : null;
    }
}
