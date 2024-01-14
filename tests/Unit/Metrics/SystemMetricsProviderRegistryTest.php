<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Metrics;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Common\System\Extension;
use SwooleBundle\SwooleBundle\Common\System\System;
use SwooleBundle\SwooleBundle\Metrics\SystemMetricsProviderRegistry;

class SystemMetricsProviderRegistryTest extends TestCase
{
    /**
     * @dataProvider extensions
     */
    public function testThrowExceptionWhenProviderForExtensionIsMissing(string $extension): void
    {
        if (!\extension_loaded($extension)) {
            self::markTestSkipped(\sprintf('Extension %s is not loaded.', $extension));
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('Metrics provider for extension "%s" not found.', $extension));

        $registry = new SystemMetricsProviderRegistry(System::create(), new \ArrayIterator([]));
        $registry->get();
    }

    public static function extensions(): array
    {
        return [
            [Extension::SWOOLE],
            [Extension::OPENSWOOLE],
        ];
    }
}
