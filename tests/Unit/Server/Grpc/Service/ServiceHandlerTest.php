<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\Service;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Grpc\CallHandler\ServerStreamCallHandler;
use SwooleBundle\SwooleBundle\Server\Grpc\CallHandler\UnaryCallHandler;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\ContextInterface;
use SwooleBundle\SwooleBundle\Server\Grpc\GrpcService;
use SwooleBundle\SwooleBundle\Server\Grpc\Registry\ServiceRegistry;
use SwooleBundle\SwooleBundle\Server\Grpc\Router\ServiceRouter;
use SwooleBundle\SwooleBundle\Server\Grpc\Serialization\ProtobufSerializerDeserializer;
use SwooleBundle\SwooleBundle\Server\Grpc\Service\ServiceHandler;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\Service\Stub\StubMessage;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\Service\Stub\StubService;

final class ServiceHandlerTest extends TestCase
{
    private ProtobufSerializerDeserializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new ProtobufSerializerDeserializer();
    }

    private function createServiceHandler(iterable $services = []): ServiceHandler
    {
        $callHandlers = [
            new UnaryCallHandler($this->serializer),
            new ServerStreamCallHandler(),
        ];

        return new ServiceHandler(
            services: $services,
            container: null,
            deserializer: $this->serializer,
            callHandlers: $callHandlers,
            interceptorChain: null,
            defaultPackage: null
        );
    }

    public function testAddService(): void
    {
        $service = new StubService();
        $handler = $this->createServiceHandler();

        $result = $handler->addService($service);

        $this->assertSame($handler, $result, 'addService should return self for fluent interface');
    }

    public function testServiceWithIterableInConstructor(): void
    {
        $service = new StubService();
        $handler = $this->createServiceHandler([$service]);

        $registry = $handler->getRegistry();

        $this->assertTrue($registry->hasService('/stub.Service'));
    }

    public function testServiceRegistration(): void
    {
        $service = new StubService();
        $handler = $this->createServiceHandler();
        $handler->addService($service);

        $registry = $handler->getRegistry();

        $this->assertTrue($registry->hasService('/stub.Service'));
        $this->assertTrue($registry->hasMethod('/stub.Service', 'UnaryMethod'));
        $this->assertTrue($registry->hasMethod('/stub.Service', 'StreamMethod'));
        $this->assertTrue($registry->hasMethod('/stub.Service', 'ErrorMethod'));
    }

    public function testGetRegistry(): void
    {
        $handler = $this->createServiceHandler();
        $registry = $handler->getRegistry();

        $this->assertInstanceOf(ServiceRegistry::class, $registry);
    }

    public function testGetRouter(): void
    {
        $handler = $this->createServiceHandler();
        $router = $handler->getRouter();

        $this->assertInstanceOf(ServiceRouter::class, $router);
    }

    public function testMultipleServices(): void
    {
        $service1 = new StubService();
        $service2 = new class implements GrpcService {
            public const NAME = '/test.Service2';

            public function testMethod(ContextInterface $context, StubMessage $request): StubMessage
            {
                return new StubMessage();
            }
        };

        $handler = $this->createServiceHandler([$service1, $service2]);
        $registry = $handler->getRegistry();

        $this->assertTrue($registry->hasService('/stub.Service'));
        $this->assertTrue($registry->hasService('/test.Service2'));
        $this->assertEquals(2, $registry->count());
    }

    public function testDefaultPackage(): void
    {
        $callHandlers = [
            new UnaryCallHandler($this->serializer),
            new ServerStreamCallHandler(),
        ];

        $handler = new ServiceHandler(
            services: [],
            container: null,
            deserializer: $this->serializer,
            callHandlers: $callHandlers,
            interceptorChain: null,
            defaultPackage: 'myapp'
        );

        // Service without explicit package should use default
        $service = new class {
            public function testMethod(ContextInterface $context, StubMessage $request): StubMessage
            {
                return new StubMessage();
            }
        };

        $handler->addService($service);
        $registry = $handler->getRegistry();

        // Should be registered with default package
        $services = $registry->getAllServices();
        $this->assertCount(1, $services);
    }
}
