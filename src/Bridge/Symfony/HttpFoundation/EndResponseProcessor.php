<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation;

use Swoole\Http\Response as SwooleResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

final class EndResponseProcessor implements ResponseProcessor
{
    public function process(HttpFoundationResponse $httpFoundationResponse, SwooleResponse $swooleResponse): void
    {
        if ($httpFoundationResponse instanceof BinaryFileResponse) {
            $swooleResponse->sendfile($httpFoundationResponse->getFile()->getRealPath());
        } else {
            $content = $httpFoundationResponse->getContent();
            $swooleResponse->end($content !== false ? $content : null);
        }
    }
}
