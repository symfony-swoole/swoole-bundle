<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Bridge\Swoole;

use K911\Swoole\Bridge\Swoole\Swoole;
use K911\Swoole\Bridge\Swoole\WaitGroup;
use K911\Swoole\Common\System\Extension;
use PHPUnit\Framework\TestCase;

class SwooleTest extends TestCase
{
    public function testCpuCoresCount(): void
    {
        if (!\extension_loaded(Extension::SWOOLE)) {
            self::markTestSkipped(\sprintf('Extension %s is not loaded.', Extension::SWOOLE));
        }

        $swoole = new Swoole();
        $cpuCoresCount = $swoole->cpuCoresCount();

        $this->assertGreaterThan(0, $cpuCoresCount);
    }

    public function testWaitGroup(): void
    {
        if (!\extension_loaded(Extension::SWOOLE)) {
            self::markTestSkipped(\sprintf('Extension %s is not loaded.', Extension::SWOOLE));
        }

        $swoole = new Swoole();
        $waitGroup = $swoole->waitGroup();

        $this->assertInstanceOf(WaitGroup::class, $waitGroup);
    }
}
