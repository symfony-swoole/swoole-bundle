<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Router;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Router\LockingLoader;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;

final class LockingLoaderTest extends TestCase
{
    public function testLoadFirstCallDelegatesToDecoratedAndAcquiresMutex(): void
    {
        $decorated = $this->createMock(LoaderInterface::class);
        $mutex = $this->createMock(Mutex::class);

        $mutex->expects($this->once())->method('acquire');
        $mutex->expects($this->once())->method('release');
        $decorated->expects($this->once())->method('load')
            ->with('resource.yaml', 'yaml')
            ->willReturn(['routes']);

        $loader = new LockingLoader($decorated, $mutex);
        $result = $loader->load('resource.yaml', 'yaml');

        $this->assertSame(['routes'], $result);
    }

    public function testLoadSecondCallWithSameKeyReturnsCacheWithoutDelegating(): void
    {
        $decorated = $this->createMock(LoaderInterface::class);
        $mutex = $this->createMock(Mutex::class);

        $mutex->expects($this->once())->method('acquire');
        $mutex->expects($this->once())->method('release');
        $decorated->expects($this->once())->method('load')
            ->with('resource.yaml', 'yaml')
            ->willReturn(['routes']);

        $loader = new LockingLoader($decorated, $mutex);
        $result1 = $loader->load('resource.yaml', 'yaml');
        $result2 = $loader->load('resource.yaml', 'yaml');

        $this->assertSame(['routes'], $result1);
        $this->assertSame(['routes'], $result2);
    }

    public function testLoadDifferentKeysDelegatesSeparately(): void
    {
        $decorated = $this->createMock(LoaderInterface::class);
        $mutex = $this->createMock(Mutex::class);

        $mutex->expects($this->exactly(2))->method('acquire');
        $mutex->expects($this->exactly(2))->method('release');
        $decorated->expects($this->exactly(2))->method('load')
            ->willReturnMap([
                ['resource1.yaml', 'yaml', ['routes1']],
                ['resource2.yaml', 'yaml', ['routes2']],
            ]);

        $loader = new LockingLoader($decorated, $mutex);
        $result1 = $loader->load('resource1.yaml', 'yaml');
        $result2 = $loader->load('resource2.yaml', 'yaml');

        $this->assertSame(['routes1'], $result1);
        $this->assertSame(['routes2'], $result2);
    }

    public function testSupportsFirstCallDelegatesToDecoratedAndAcquiresMutex(): void
    {
        $decorated = $this->createMock(LoaderInterface::class);
        $mutex = $this->createMock(Mutex::class);

        $mutex->expects($this->once())->method('acquire');
        $mutex->expects($this->once())->method('release');
        $decorated->expects($this->once())->method('supports')
            ->with('resource.yaml', 'yaml')
            ->willReturn(true);

        $loader = new LockingLoader($decorated, $mutex);

        $this->assertTrue($loader->supports('resource.yaml', 'yaml'));
    }

    public function testSupportsSecondCallReturnsCachedResult(): void
    {
        $decorated = $this->createMock(LoaderInterface::class);
        $mutex = $this->createMock(Mutex::class);

        $mutex->expects($this->once())->method('acquire');
        $mutex->expects($this->once())->method('release');
        $decorated->expects($this->once())->method('supports')
            ->with('resource.yaml', 'yaml')
            ->willReturn(false);

        $loader = new LockingLoader($decorated, $mutex);
        $result1 = $loader->supports('resource.yaml', 'yaml');
        $result2 = $loader->supports('resource.yaml', 'yaml');

        $this->assertFalse($result1);
        $this->assertFalse($result2);
    }

    public function testGetResolverDelegatesToDecorated(): void
    {
        $decorated = $this->createMock(LoaderInterface::class);
        $mutex = $this->createStub(Mutex::class);
        $resolver = $this->createStub(LoaderResolverInterface::class);

        $decorated->expects($this->once())->method('getResolver')->willReturn($resolver);

        $loader = new LockingLoader($decorated, $mutex);

        $this->assertSame($resolver, $loader->getResolver());
    }

    public function testSetResolverDelegatesToDecorated(): void
    {
        $decorated = $this->createMock(LoaderInterface::class);
        $mutex = $this->createStub(Mutex::class);
        $resolver = $this->createStub(LoaderResolverInterface::class);

        $decorated->expects($this->once())->method('setResolver')->with($resolver);

        $loader = new LockingLoader($decorated, $mutex);
        $loader->setResolver($resolver);
    }
}
