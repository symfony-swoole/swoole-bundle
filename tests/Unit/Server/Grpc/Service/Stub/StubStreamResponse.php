<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\Service\Stub;

use SwooleBundle\SwooleBundle\Server\Grpc\Context\ContextInterface;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\StreamResponseInterface;

class StubStreamResponse implements StreamResponseInterface
{
    public function __construct(ContextInterface $context)
    {
    }

    public function send(StubMessage $message): void
    {
    }
}
