<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Common\System;

use K911\Swoole\Common\System\Extension;
use K911\Swoole\Common\System\System;
use K911\Swoole\Metrics\SystemMetricsProviderRegistry;
use PHPUnit\Framework\TestCase;

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
