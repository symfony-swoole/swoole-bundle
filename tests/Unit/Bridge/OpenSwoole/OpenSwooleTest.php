<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\OpenSwoole;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\OpenSwoole\OpenSwoole;
use SwooleBundle\SwooleBundle\Bridge\OpenSwoole\WaitGroup;
use SwooleBundle\SwooleBundle\Common\System\Extension;

final class OpenSwooleTest extends TestCase
{
    public function testCpuCoresCount(): void
    {
        if (!extension_loaded(Extension::OPENSWOOLE)) {
            self::markTestSkipped(sprintf('Extension %s is not loaded.', Extension::OPENSWOOLE));
        }

        $swoole = new OpenSwoole();
        $cpuCoresCount = $swoole->cpuCoresCount();

        $this->assertGreaterThan(0, $cpuCoresCount);
    }

    public function testWaitGroup(): void
    {
        if (!extension_loaded(Extension::OPENSWOOLE)) {
            self::markTestSkipped(sprintf('Extension %s is not loaded.', Extension::OPENSWOOLE));
        }

        $swoole = new OpenSwoole();
        $waitGroup = $swoole->waitGroup();

        $this->assertInstanceOf(WaitGroup::class, $waitGroup);
    }
}
