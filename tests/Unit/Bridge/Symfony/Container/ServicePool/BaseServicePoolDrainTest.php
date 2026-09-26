<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Container\ServicePool;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\BaseServicePool;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\UnmanagedFactoryServicePool;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleSpy;

/**
 * What a drained pool lets go of, and what it keeps.
 */
#[CoversClass(BaseServicePool::class)]
final class BaseServicePoolDrainTest extends TestCase
{
    public function testAFreeInstanceIsLetGoOfAndTheNextOneIsBuiltAfresh(): void
    {
        $pool = self::pool();
        $first = $pool->get();
        $pool->releaseFromCoroutine();

        $pool->drain();

        self::assertNotSame($first, $pool->get());
    }

    /**
     * An instance assigned to a coroutine is that coroutine's to use until it ends - draining it would destroy
     * something still in use.
     */
    public function testAnAssignedInstanceIsKept(): void
    {
        $pool = self::pool();
        $assigned = $pool->get();

        $pool->drain();

        self::assertSame($assigned, $pool->getAssigned());
        self::assertSame($assigned, $pool->get());
    }

    /**
     * @return UnmanagedFactoryServicePool<stdClass>
     */
    private static function pool(): UnmanagedFactoryServicePool
    {
        return new UnmanagedFactoryServicePool(
            static fn(): stdClass => new stdClass(),
            new SwooleSpy(),
            new class implements Mutex {
                #[Override]
                public function acquire(): void {}

                #[Override]
                public function release(): void {}

                #[Override]
                public function isAcquired(): bool
                {
                    return false;
                }
            },
        );
    }
}
