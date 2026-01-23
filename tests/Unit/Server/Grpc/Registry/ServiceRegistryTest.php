<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\Registry;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Grpc\Exception\ServiceException;
use SwooleBundle\SwooleBundle\Server\Grpc\Registry\ServiceRegistry;
use SwooleBundle\SwooleBundle\Server\Grpc\Service\ServiceMethodDefinition;
use SwooleBundle\SwooleBundle\Server\Grpc\Constant;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\Service\Stub\StubService;

final class ServiceRegistryTest extends TestCase
{
    private ServiceRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new ServiceRegistry();
    }

    public function testRegisterService(): void
    {
        $service = new StubService();
        $methodDefinition = new ServiceMethodDefinition(
            name: 'testMethod',
            paramType: 'TestRequest',
            returnType: 'TestResponse',
            type: Constant::GRPC_CALL_TYPE_UNARY
        );

        $this->registry->register($service, '/test.Service', ['testMethod' => $methodDefinition]);

        $this->assertTrue($this->registry->hasService('/test.Service'));
    }

    public function testHasService(): void
    {
        $service = new StubService();
        $this->registry->register($service, '/test.Service', []);

        $this->assertTrue($this->registry->hasService('/test.Service'));
        $this->assertFalse($this->registry->hasService('/nonexistent.Service'));
    }

    public function testGetService(): void
    {
        $service = new StubService();
        $this->registry->register($service, '/test.Service', []);

        $retrieved = $this->registry->getService('/test.Service');

        $this->assertSame($service, $retrieved);
    }

    public function testGetServiceNotFound(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Service not found: /nonexistent.Service');

        $this->registry->getService('/nonexistent.Service');
    }

    public function testHasMethod(): void
    {
        $service = new StubService();
        $methodDefinition = new ServiceMethodDefinition(
            name: 'testMethod',
            paramType: 'TestRequest',
            returnType: 'TestResponse',
            type: Constant::GRPC_CALL_TYPE_UNARY
        );

        $this->registry->register($service, '/test.Service', ['testMethod' => $methodDefinition]);

        $this->assertTrue($this->registry->hasMethod('/test.Service', 'testMethod'));
        $this->assertFalse($this->registry->hasMethod('/test.Service', 'nonexistent'));
    }

    public function testGetMethod(): void
    {
        $service = new StubService();
        $methodDefinition = new ServiceMethodDefinition(
            name: 'testMethod',
            paramType: 'TestRequest',
            returnType: 'TestResponse',
            type: Constant::GRPC_CALL_TYPE_UNARY
        );

        $this->registry->register($service, '/test.Service', ['testMethod' => $methodDefinition]);

        $retrieved = $this->registry->getMethod('/test.Service', 'testMethod');

        $this->assertSame($methodDefinition, $retrieved);
        $this->assertEquals('testMethod', $retrieved->name);
    }

    public function testGetMethodNotFound(): void
    {
        $service = new StubService();
        $this->registry->register($service, '/test.Service', []);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Method not found: /test.Service/nonexistent');

        $this->registry->getMethod('/test.Service', 'nonexistent');
    }

    public function testGetAllServices(): void
    {
        $service1 = new StubService();
        $service2 = new class {
            public function test() {}
        };

        $this->registry->register($service1, '/service1', []);
        $this->registry->register($service2, '/service2', []);

        $all = $this->registry->getAllServices();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('/service1', $all);
        $this->assertArrayHasKey('/service2', $all);
    }

    public function testGetServiceMethods(): void
    {
        $service = new StubService();
        $method1 = new ServiceMethodDefinition('method1', 'Req', 'Res', Constant::GRPC_CALL_TYPE_UNARY);
        $method2 = new ServiceMethodDefinition('method2', 'Req', 'Res', Constant::GRPC_CALL_TYPE_UNARY);

        $this->registry->register($service, '/test.Service', [
            'method1' => $method1,
            'method2' => $method2,
        ]);

        $methods = $this->registry->getServiceMethods('/test.Service');

        $this->assertCount(2, $methods);
        $this->assertArrayHasKey('method1', $methods);
        $this->assertArrayHasKey('method2', $methods);
    }

    public function testGetServiceMethodsForNonExistentService(): void
    {
        $methods = $this->registry->getServiceMethods('/nonexistent');

        $this->assertIsArray($methods);
        $this->assertEmpty($methods);
    }

    public function testCount(): void
    {
        $this->assertEquals(0, $this->registry->count());

        $service1 = new StubService();
        $this->registry->register($service1, '/service1', []);

        $this->assertEquals(1, $this->registry->count());

        $service2 = new class {};
        $this->registry->register($service2, '/service2', []);

        $this->assertEquals(2, $this->registry->count());
    }

    public function testRegisterDuplicateServiceThrowsException(): void
    {
        $service1 = new StubService();
        $service2 = new class {};

        $this->registry->register($service1, '/test.Service', []);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Service already registered: /test.Service');

        $this->registry->register($service2, '/test.Service', []);
    }
}
