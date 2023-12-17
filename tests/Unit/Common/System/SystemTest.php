<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Common\System;

use K911\Swoole\Common\System\Extension;
use K911\Swoole\Common\System\System;
use OpenSwoole\Util;
use PHPUnit\Framework\TestCase;

class SystemTest extends TestCase
{
    /**
     * @dataProvider extensions
     */
    public function testSystemCreation(string $extension, callable $versionFn): void
    {
        if (!\extension_loaded($extension)) {
            self::markTestSkipped(\sprintf('Extension %s is not loaded.', $extension));
        }

        $system = System::create();

        $this->assertSame($extension, $system->extension()->toString());
        $this->assertSame($versionFn(), $system->version()->toString());
    }

    public static function extensions(): array
    {
        return [
            [Extension::SWOOLE, fn (): string => \swoole_version()],
            [Extension::OPENSWOOLE, fn (): string => Util::getVersion()],
        ];
    }
}
