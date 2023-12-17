<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Common\System;

use K911\Swoole\Common\System\Extension;
use K911\Swoole\Common\System\Version;
use OpenSwoole\Util;
use PHPUnit\Framework\TestCase;

class VersionTest extends TestCase
{
    public function testVersionCreation(): void
    {
        if (\extension_loaded(Extension::SWOOLE)) {
            $versionString = \swoole_version();
        } elseif (\extension_loaded(Extension::OPENSWOOLE)) {
            $versionString = Util::getVersion();
        } else {
            self::markTestSkipped('No Swoole or OpenSwoole extension loaded.');
        }

        $version = Version::fromVersionString($versionString);

        $this->assertSame($versionString, $version->toString());
    }
}
