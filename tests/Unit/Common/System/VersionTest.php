<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Common\System;

use OpenSwoole\Util;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Common\System\Extension;
use SwooleBundle\SwooleBundle\Common\System\Version;

final class VersionTest extends TestCase
{
    public function testVersionCreation(): void
    {
        if (extension_loaded(Extension::SWOOLE)) {
            $versionString = swoole_version();
        } elseif (extension_loaded(Extension::OPENSWOOLE)) {
            $versionString = Util::getVersion();
        } else {
            self::markTestSkipped('No Swoole or OpenSwoole extension loaded.');
        }

        $version = Version::fromVersionString($versionString);

        $this->assertSame($versionString, $version->toString());
    }
}
