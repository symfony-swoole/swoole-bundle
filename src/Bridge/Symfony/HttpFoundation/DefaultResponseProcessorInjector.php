<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation;

use Swoole\Http\Response as SwooleResponse;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

final class DefaultResponseProcessorInjector implements ResponseProcessorInjector
{
    public function __construct(private readonly ResponseProcessor $responseProcessor) {}

    public function injectProcessor(HttpFoundationRequest $request, SwooleResponse $swooleResponse): void
    {
        $request->attributes->set(
            self::ATTR_KEY_RESPONSE_PROCESSOR,
            function (HttpFoundationResponse $httpFoundationResponse) use ($swooleResponse): void {
                $this->responseProcessor->process($httpFoundationResponse, $swooleResponse);
            }
        );
    }
}
