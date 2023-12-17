<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Bridge\OpenSwoole;

use K911\Swoole\Bridge\OpenSwoole\OpenSwoole;
use K911\Swoole\Bridge\OpenSwoole\WaitGroup;
use K911\Swoole\Common\System\Extension;
use PHPUnit\Framework\TestCase;

class OpenSwooleTest extends TestCase
{
    public function testCpuCoresCount(): void
    {
        if (!\extension_loaded(Extension::OPENSWOOLE)) {
            self::markTestSkipped(\sprintf('Extension %s is not loaded.', Extension::OPENSWOOLE));
        }

        $swoole = new OpenSwoole();
        $cpuCoresCount = $swoole->cpuCoresCount();

        $this->assertGreaterThan(0, $cpuCoresCount);
    }

    public function testWaitGroup(): void
    {
        if (!\extension_loaded(Extension::OPENSWOOLE)) {
            self::markTestSkipped(\sprintf('Extension %s is not loaded.', Extension::OPENSWOOLE));
        }

        $swoole = new OpenSwoole();
        $waitGroup = $swoole->waitGroup();

        $this->assertInstanceOf(WaitGroup::class, $waitGroup);
    }
}
