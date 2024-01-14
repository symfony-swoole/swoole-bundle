<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Common\System;

use OpenSwoole\Util;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Common\System\Extension;
use SwooleBundle\SwooleBundle\Common\System\System;

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
