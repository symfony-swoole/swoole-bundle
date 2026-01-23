<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\Service;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Swoole\Http\Response as SwooleResponse;
use SwooleBundle\SwooleBundle\Server\Grpc\CallHandler\UnaryCallHandler;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\Context;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\Request;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\Response;
use SwooleBundle\SwooleBundle\Server\Grpc\Interceptor\Interceptor;
use SwooleBundle\SwooleBundle\Server\Grpc\Interceptor\InterceptorChain;
use SwooleBundle\SwooleBundle\Server\Grpc\Serialization\ProtobufSerializerDeserializer;
use SwooleBundle\SwooleBundle\Server\Grpc\Service\ServiceHandler;
use SwooleBundle\SwooleBundle\Server\HttpServer;
use SwooleBundle\SwooleBundle\Server\HttpServerConfiguration;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\Service\Stub\StubService;

final class TestInterceptor implements Interceptor
{
    public int $callCount = 0;

    public function intercept(Context $context, callable $next): Context
    {
        $this->callCount++;
        $context->withAttribute('interceptor_called', true);

        return $next($context);
    }

    public function getPriority(): int
    {
        return 0;
    }
}

final class ServiceHandlerInterceptorTest extends TestCase
{
    private ProtobufSerializerDeserializer $serializer;
    private HttpServer $server;

    protected function setUp(): void
    {
        $this->serializer = new ProtobufSerializerDeserializer();
        $config = $this->createMock(HttpServerConfiguration::class);
        $this->server = new HttpServer($config);
    }

    public function testInterceptorsDisabledByDefault(): void
    {
        $interceptor = new TestInterceptor();
        $interceptorChain = new InterceptorChain([$interceptor]);

        $handler = new ServiceHandler(
            services: [new StubService()],
            container: null,
            deserializer: $this->serializer,
            callHandlers: [new UnaryCallHandler($this->serializer)],
            interceptorChain: $interceptorChain,
            defaultPackage: null,
            interceptorsEnabled: false // Explicitly disabled
        );

        $context = $this->createContext('/stub.Service', 'UnaryMethod');
        $handler->handle($context);

        // Interceptor should NOT have been called
        $this->assertEquals(0, $interceptor->callCount);
        $this->assertNull($context->getAttribute('interceptor_called'));
    }

    public function testInterceptorsEnabledWorks(): void
    {
        $interceptor = new TestInterceptor();
        $interceptorChain = new InterceptorChain([$interceptor]);

        $handler = new ServiceHandler(
            services: [new StubService()],
            container: null,
            deserializer: $this->serializer,
            callHandlers: [new UnaryCallHandler($this->serializer)],
            interceptorChain: $interceptorChain,
            defaultPackage: null,
            interceptorsEnabled: true // Explicitly enabled
        );

        $context = $this->createContext('/stub.Service', 'UnaryMethod');
        $handler->handle($context);

        // Interceptor SHOULD have been called
        $this->assertEquals(1, $interceptor->callCount);
        $this->assertTrue($context->getAttribute('interceptor_called'));
    }

    public function testInterceptorsDisabledWithNullChain(): void
    {
        $handler = new ServiceHandler(
            services: [new StubService()],
            container: null,
            deserializer: $this->serializer,
            callHandlers: [new UnaryCallHandler($this->serializer)],
            interceptorChain: null, // No chain provided
            defaultPackage: null,
            interceptorsEnabled: false
        );

        $context = $this->createContext('/stub.Service', 'UnaryMethod');
        $result = $handler->handle($context);

        // Should work fine without interceptors
        $this->assertInstanceOf(Context::class, $result);
        $this->assertEquals('payload', $result->getResponse()->getPayload());
    }

    public function testInterceptorsEnabledButNullChain(): void
    {
        $handler = new ServiceHandler(
            services: [new StubService()],
            container: null,
            deserializer: $this->serializer,
            callHandlers: [new UnaryCallHandler($this->serializer)],
            interceptorChain: null, // No chain provided
            defaultPackage: null,
            interceptorsEnabled: true // Enabled but no chain = same as disabled
        );

        $context = $this->createContext('/stub.Service', 'UnaryMethod');
        $result = $handler->handle($context);

        // Should still work, just no interceptors executed
        $this->assertInstanceOf(Context::class, $result);
        $this->assertEquals('payload', $result->getResponse()->getPayload());
    }

    private function createContext(string $serviceName, string $methodName): Context
    {
        // Create Swoole\Http\Request using reflection
        $reflection = new ReflectionClass(\Swoole\Http\Request::class);
        $swooleRequest = $reflection->newInstanceWithoutConstructor();

        // Set header property
        $headerProp = $reflection->getProperty('header');
        $headerProp->setValue($swooleRequest, [
            'content-type' => 'application/grpc',
            'te' => 'trailers',
        ]);

        // Set server property
        $serverProp = $reflection->getProperty('server');
        $serverProp->setValue($swooleRequest, [
            'request_uri' => $serviceName . '/' . $methodName,
        ]);

        $request = new Request($swooleRequest);
        $request->init();

        $swooleResponse = $this->createMock(SwooleResponse::class);
        $response = new Response($swooleResponse);

        return new Context($this->server, $request, $response);
    }
}
