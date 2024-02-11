<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Common\System;

use OpenSwoole\Util;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Common\System\Extension;
use SwooleBundle\SwooleBundle\Common\System\System;

final class SystemTest extends TestCase
{
    #[DataProvider('extensions')]
    public function testSystemCreation(string $extension, callable $versionFn): void
    {
        if (!extension_loaded($extension)) {
            self::markTestSkipped(sprintf('Extension %s is not loaded.', $extension));
        }

        $system = System::create();

        $this->assertSame($extension, $system->extension()->toString());
        $this->assertSame($versionFn(), $system->version()->toString());
    }

    /**
     * @return array<array{0: string}>
     */
    public static function extensions(): array
    {
        return [
            [Extension::SWOOLE, static fn(): string => swoole_version()],
            [Extension::OPENSWOOLE, static fn(): string => Util::getVersion()],
        ];
    }
}
