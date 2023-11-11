<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Common\System;

use K911\Swoole\Common\System\Version;
use PHPUnit\Framework\TestCase;

class VersionTest extends TestCase
{
    public function testVersionCreation(): void
    {
        $versionString = \swoole_version();
        $version = Version::create();

        $this->assertSame($versionString, $version->toString());
    }
}
