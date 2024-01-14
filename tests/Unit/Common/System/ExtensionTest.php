<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Common\System;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Common\System\Extension;

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
