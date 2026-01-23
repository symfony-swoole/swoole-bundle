<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\Registry;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Grpc\Attribute\GrpcService;
use SwooleBundle\SwooleBundle\Server\Grpc\Context\ContextInterface;
use SwooleBundle\SwooleBundle\Server\Grpc\Exception\ServiceException;
use SwooleBundle\SwooleBundle\Server\Grpc\GrpcService as GrpcServiceInterface;
use SwooleBundle\SwooleBundle\Server\Grpc\Registry\ServiceRegistry;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\Service\Stub\StubMessage;

// Test services with different package configurations
#[GrpcService(package: 'myapp')]
class PackagedService
{
    public function testMethod(ContextInterface $context, StubMessage $request): StubMessage
    {
        return new StubMessage();
    }
}

#[GrpcService(package: 'myapp.v1')]
class VersionedService
{
    public function testMethod(ContextInterface $context, StubMessage $request): StubMessage
    {
        return new StubMessage();
    }
}

#[GrpcService]
class NoPackageService
{
    public function testMethod(ContextInterface $context, StubMessage $request): StubMessage
    {
        return new StubMessage();
    }
}

class InterfaceBasedService implements GrpcServiceInterface
{
    public const NAME = '/admin.AdminService';

    public function testMethod(ContextInterface $context, StubMessage $request): StubMessage
    {
        return new StubMessage();
    }
}

final class ServiceRegistryPackageTest extends TestCase
{
    private ServiceRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new ServiceRegistry();
    }

    public function testRegisterServiceWithPackage(): void
    {
        $service = new PackagedService();

        $this->registry->register($service, '/myapp.PackagedService', []);

        $this->assertTrue($this->registry->hasService('/myapp.PackagedService'));
        $this->assertEquals('myapp', $this->registry->getServicePackage('/myapp.PackagedService'));
    }

    public function testRegisterServiceWithoutPackage(): void
    {
        $service = new NoPackageService();

        $this->registry->register($service, '/NoPackageService', []);

        $this->assertTrue($this->registry->hasService('/NoPackageService'));
        $this->assertNull($this->registry->getServicePackage('/NoPackageService'));
    }

    public function testRegisterServiceWithNestedPackage(): void
    {
        $service = new VersionedService();

        $this->registry->register($service, '/myapp.v1.VersionedService', []);

        $this->assertTrue($this->registry->hasService('/myapp.v1.VersionedService'));
        $this->assertEquals('myapp.v1', $this->registry->getServicePackage('/myapp.v1.VersionedService'));
    }

    public function testHasPackage(): void
    {
        $service1 = new PackagedService();
        $service2 = new NoPackageService();

        $this->registry->register($service1, '/myapp.PackagedService', []);
        $this->registry->register($service2, '/NoPackageService', []);

        $this->assertTrue($this->registry->hasPackage('myapp'));
        $this->assertTrue($this->registry->hasPackage(null)); // Services without package
        $this->assertFalse($this->registry->hasPackage('admin'));
    }

    public function testGetServicesByPackage(): void
    {
        $service1 = new PackagedService();
        $service2 = new VersionedService();
        $service3 = new NoPackageService();

        $this->registry->register($service1, '/myapp.ServiceA', []);
        $this->registry->register($service2, '/myapp.ServiceB', []);
        $this->registry->register($service3, '/NoPackageService', []);

        $myappServices = $this->registry->getServicesByPackage('myapp');

        $this->assertCount(2, $myappServices);
        $this->assertContains('/myapp.ServiceA', $myappServices);
        $this->assertContains('/myapp.ServiceB', $myappServices);
    }

    public function testGetServicesByPackageWithNested(): void
    {
        $service1 = new VersionedService();

        $this->registry->register($service1, '/myapp.v1.VersionedService', []);

        $services = $this->registry->getServicesByPackage('myapp.v1');

        $this->assertCount(1, $services);
        $this->assertContains('/myapp.v1.VersionedService', $services);
    }

    public function testGetServicesByNullPackage(): void
    {
        $service1 = new NoPackageService();
        $service2 = new PackagedService();

        $this->registry->register($service1, '/ServiceA', []);
        $this->registry->register($service2, '/myapp.ServiceB', []);

        $noPackageServices = $this->registry->getServicesByPackage(null);

        $this->assertCount(1, $noPackageServices);
        $this->assertContains('/ServiceA', $noPackageServices);
    }

    public function testGetAllPackages(): void
    {
        $service1 = new PackagedService();
        $service2 = new VersionedService();
        $service3 = new NoPackageService();
        $service4 = new InterfaceBasedService();

        $this->registry->register($service1, '/myapp.ServiceA', []);
        $this->registry->register($service2, '/myapp.v1.ServiceB', []);
        $this->registry->register($service3, '/NoPackageService', []);
        $this->registry->register($service4, '/admin.AdminService', []);

        $packages = $this->registry->getAllPackages();

        $this->assertCount(4, $packages);
        $this->assertContains('myapp', $packages);
        $this->assertContains('myapp.v1', $packages);
        $this->assertContains('admin', $packages);

        // Verify null package exists by checking hasPackage
        $this->assertTrue($this->registry->hasPackage(null));
    }

    public function testValidateServicePackage(): void
    {
        $service = new PackagedService();
        $this->registry->register($service, '/myapp.PackagedService', []);

        // Should not throw exception when package matches
        $this->registry->validateServicePackage('/myapp.PackagedService', 'myapp');

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage("Package mismatch");

        // Should throw when package doesn't match
        $this->registry->validateServicePackage('/myapp.PackagedService', 'admin');
    }

    public function testValidateServicePackageWithNullExpected(): void
    {
        $service = new NoPackageService();
        $this->registry->register($service, '/NoPackageService', []);

        // Should not throw when validating service with no package
        $this->registry->validateServicePackage('/NoPackageService', null);

        $this->assertTrue(true); // Assertion to confirm no exception
    }

    public function testValidateServicePackageNotFound(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage("Cannot validate package: service not found");

        $this->registry->validateServicePackage('/NonExistent.Service', 'myapp');
    }

    public function testMultipleServicesInSamePackage(): void
    {
        $service1 = new PackagedService();
        $service2 = new class {
            public function test(ContextInterface $context, StubMessage $request): StubMessage
            {
                return new StubMessage();
            }
        };

        $this->registry->register($service1, '/myapp.UserService', []);
        $this->registry->register($service2, '/myapp.OrderService', []);

        $services = $this->registry->getServicesByPackage('myapp');

        $this->assertCount(2, $services);
        $this->assertEquals('myapp', $this->registry->getServicePackage('/myapp.UserService'));
        $this->assertEquals('myapp', $this->registry->getServicePackage('/myapp.OrderService'));
    }

    public function testPackageExtraction(): void
    {
        $service1 = new PackagedService();
        $service2 = new VersionedService();
        $service3 = new NoPackageService();

        $this->registry->register($service1, '/myapp.Service', []);
        $this->registry->register($service2, '/myapp.v1.v2.Service', []);
        $this->registry->register($service3, '/Service', []);

        $this->assertEquals('myapp', $this->registry->getServicePackage('/myapp.Service'));
        $this->assertEquals('myapp.v1.v2', $this->registry->getServicePackage('/myapp.v1.v2.Service'));
        $this->assertNull($this->registry->getServicePackage('/Service'));
    }
}
