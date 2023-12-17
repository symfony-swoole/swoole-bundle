<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Common\System;

use K911\Swoole\Common\System\Extension;
use PHPUnit\Framework\TestCase;

class ExtensionTest extends TestCase
{
    /**
     * @dataProvider extensions
     */
    public function testExtensionCreation(string $extensionName): void
    {
        if (!\extension_loaded($extensionName)) {
            self::markTestSkipped(\sprintf('Extension %s is not loaded.', $extensionName));
        }

        $extension = Extension::create();
        $extension2 = Extension::{$extensionName}();
        $isExtensionMethod = 'is'.$extensionName;

        $this->assertSame($extensionName, $extension->toString());
        $this->assertSame($extensionName, $extension2->toString());
        $this->assertTrue($extension->$isExtensionMethod());
    }

    public static function extensions(): array
    {
        return [
            [Extension::SWOOLE],
            [Extension::OPENSWOOLE],
        ];
    }
}
