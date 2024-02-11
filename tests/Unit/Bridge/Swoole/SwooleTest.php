<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Swoole;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Swoole\Swoole;
use SwooleBundle\SwooleBundle\Bridge\Swoole\WaitGroup;
use SwooleBundle\SwooleBundle\Common\System\Extension;

final class SwooleTest extends TestCase
{
    public function testCpuCoresCount(): void
    {
        if (!extension_loaded(Extension::SWOOLE)) {
            self::markTestSkipped(sprintf('Extension %s is not loaded.', Extension::SWOOLE));
        }

        $swoole = new Swoole();
        $cpuCoresCount = $swoole->cpuCoresCount();

        $this->assertGreaterThan(0, $cpuCoresCount);
    }

    public function testWaitGroup(): void
    {
        if (!extension_loaded(Extension::SWOOLE)) {
            self::markTestSkipped(sprintf('Extension %s is not loaded.', Extension::SWOOLE));
        }

        $swoole = new Swoole();
        $waitGroup = $swoole->waitGroup();

        $this->assertInstanceOf(WaitGroup::class, $waitGroup);
    }
}
