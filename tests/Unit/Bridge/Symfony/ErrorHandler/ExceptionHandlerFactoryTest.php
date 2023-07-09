<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Bridge\Symfony\ErrorHandler;

use K911\Swoole\Bridge\Symfony\ErrorHandler\ExceptionHandlerFactory;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernel;

final class ExceptionHandlerFactoryTest extends TestCase
{
    use ProphecyTrait;

    public function testCreatedExceptionHandler(): void
    {
        $error = new \Error('Error');
        $kernelMock = $this->prophesize(HttpKernel::class)->reveal();
        $requestMock = $this->prophesize(Request::class)->reveal();
        $responseMock = $this->prophesize(Response::class)->reveal();
        $throwableHandlerProphecy = $this->prophesize(\ReflectionMethod::class);
        $throwableHandlerProphecy->invoke()->withArguments([
            $kernelMock,
            $error,
            $requestMock,
            HttpKernel::MAIN_REQUEST,
        ])->willReturn($responseMock);
        $throwableHandlerMock = $throwableHandlerProphecy->reveal();

        $factory = new ExceptionHandlerFactory($kernelMock, $throwableHandlerMock);
        $handler = $factory->newExceptionHandler($requestMock);
        $handler($error);
        $response = $handler->getResponse();

        self::assertSame($responseMock, $response);
    }
}
