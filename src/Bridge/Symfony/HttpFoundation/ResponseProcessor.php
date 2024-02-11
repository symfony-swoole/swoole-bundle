<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation;

use Swoole\Http\Response as SwooleResponse;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

interface ResponseProcessor
{
    public function process(HttpFoundationResponse $httpFoundationResponse, SwooleResponse $swooleSwooleResponse): void;
}
