<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Common\System;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Common\System\Extension;

final class ExtensionTest extends TestCase
{
    #[DataProvider('extensions')]
    public function testExtensionCreation(string $extensionName): void
    {
        if (!extension_loaded($extensionName)) {
            self::markTestSkipped(sprintf('Extension %s is not loaded.', $extensionName));
        }

        $extension = Extension::create();
        $extension2 = Extension::{$extensionName}();
        $isExtensionMethod = 'is' . $extensionName;

        $this->assertSame($extensionName, $extension->toString());
        $this->assertSame($extensionName, $extension2->toString());
        $this->assertTrue($extension->$isExtensionMethod());
    }

    /**
     * @return array<array{0: string}>
     */
    public static function extensions(): array
    {
        return [
            [Extension::SWOOLE],
            [Extension::OPENSWOOLE],
        ];
    }
}
