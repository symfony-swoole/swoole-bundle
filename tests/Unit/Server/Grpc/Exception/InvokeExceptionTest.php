<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\Exception;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Grpc\Enum\Status;
use SwooleBundle\SwooleBundle\Server\Grpc\Exception\GRPCException;
use SwooleBundle\SwooleBundle\Server\Grpc\Exception\InvokeException;

final class InvokeExceptionTest extends TestCase
{
    public function testDefaultCodeIsUnavailable(): void
    {
        $exception = new InvokeException('service unavailable');

        $this->assertSame(Status::UNAVAILABLE->value, $exception->getCode());
        $this->assertSame('service unavailable', $exception->getMessage());
        $this->assertInstanceOf(GRPCException::class, $exception);
    }

    public function testCreateWithCustomCode(): void
    {
        $exception = InvokeException::create('invalid argument', Status::INVALID_ARGUMENT);

        $this->assertInstanceOf(InvokeException::class, $exception);
        $this->assertSame(Status::INVALID_ARGUMENT->value, $exception->getCode());
        $this->assertSame('invalid argument', $exception->getMessage());
    }
}
