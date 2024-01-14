<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server;

use SwooleBundle\SwooleBundle\Common\System\System;

final class SwooleServerMockFactory
{
    public static function make(bool $taskworker = false): SwooleServerMock
    {
        $system = System::create();
        $versionString = $system->version()->toString();

        if (str_starts_with($versionString, '22.') || str_starts_with($versionString, '5.')) {
            return SwooleServerMock::make($taskworker);
        }

        throw new \RuntimeException(\sprintf('Unsupported Swoole version %s for extension %s.', $versionString, $system->extension()->toString()));
    }
}
