<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Router;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Router\LockingConfigCacheFactory;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheInterface;

final class LockingConfigCacheFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(LockingConfigCacheFactory::class, 'initializationCompleted');
        $ref->setValue(null, []);
    }

    public function testCacheFirstCallAcquiresMutexAndDelegatesToDecorated(): void
    {
        $decorated = $this->createMock(ConfigCacheFactoryInterface::class);
        $mutex = $this->createMock(Mutex::class);
        $configCache = $this->createStub(ConfigCacheInterface::class);
        $callable = static function (): void {};

        $mutex->expects($this->once())->method('acquire');
        $mutex->expects($this->once())->method('release');
        $decorated->expects($this->once())->method('cache')
            ->with('/path/to/cache.php', $callable)
            ->willReturn($configCache);

        $factory = new LockingConfigCacheFactory($decorated, $mutex);
        $result = $factory->cache('/path/to/cache.php', $callable);

        $this->assertSame($configCache, $result);
    }

    public function testCacheSecondCallWithSameFileBypasessMutexAndCallsDecoratedDirectly(): void
    {
        $decorated = $this->createMock(ConfigCacheFactoryInterface::class);
        $mutex = $this->createMock(Mutex::class);
        $configCache = $this->createStub(ConfigCacheInterface::class);
        $callable = static function (): void {};

        // First call goes through mutex; second call bypasses it but still calls decorated
        $mutex->expects($this->once())->method('acquire');
        $mutex->expects($this->once())->method('release');
        $decorated->expects($this->exactly(2))->method('cache')
            ->with('/path/to/cache.php', $callable)
            ->willReturn($configCache);

        $factory = new LockingConfigCacheFactory($decorated, $mutex);
        $factory->cache('/path/to/cache.php', $callable);
        $factory->cache('/path/to/cache.php', $callable);
    }

    public function testCacheDifferentFileIsIndependent(): void
    {
        $decorated = $this->createMock(ConfigCacheFactoryInterface::class);
        $mutex = $this->createMock(Mutex::class);
        $configCache1 = $this->createStub(ConfigCacheInterface::class);
        $configCache2 = $this->createStub(ConfigCacheInterface::class);
        $callable = static function (): void {};

        $mutex->expects($this->exactly(2))->method('acquire');
        $mutex->expects($this->exactly(2))->method('release');
        $decorated->expects($this->exactly(2))->method('cache')
            ->willReturnMap([
                ['/path/to/cache1.php', $callable, $configCache1],
                ['/path/to/cache2.php', $callable, $configCache2],
            ]);

        $factory = new LockingConfigCacheFactory($decorated, $mutex);
        $result1 = $factory->cache('/path/to/cache1.php', $callable);
        $result2 = $factory->cache('/path/to/cache2.php', $callable);

        $this->assertSame($configCache1, $result1);
        $this->assertSame($configCache2, $result2);
    }

    public function testStaticCacheIsSharedAcrossInstances(): void
    {
        $decoratedA = $this->createMock(ConfigCacheFactoryInterface::class);
        $mutexA = $this->createMock(Mutex::class);
        $decoratedB = $this->createMock(ConfigCacheFactoryInterface::class);
        $mutexB = $this->createMock(Mutex::class);
        $configCache = $this->createStub(ConfigCacheInterface::class);
        $callable = static function (): void {};

        // Instance A: first call acquires mutex
        $mutexA->expects($this->once())->method('acquire');
        $mutexA->expects($this->once())->method('release');
        $decoratedA->expects($this->once())->method('cache')
            ->with('/path/to/cache.php', $callable)
            ->willReturn($configCache);

        // Instance B: static cache already populated, bypasses mutex, calls decorated directly
        $mutexB->expects($this->never())->method('acquire');
        $mutexB->expects($this->never())->method('release');
        $decoratedB->expects($this->once())->method('cache')
            ->with('/path/to/cache.php', $callable)
            ->willReturn($configCache);

        $factoryA = new LockingConfigCacheFactory($decoratedA, $mutexA);
        $factoryA->cache('/path/to/cache.php', $callable);

        $factoryB = new LockingConfigCacheFactory($decoratedB, $mutexB);
        $factoryB->cache('/path/to/cache.php', $callable);
    }
}
