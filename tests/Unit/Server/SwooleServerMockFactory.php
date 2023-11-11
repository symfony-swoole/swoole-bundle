<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Server;

use K911\Swoole\Tests\Unit\Server\SwooleServerMock\SwooleServerMockOpenSwoole4;
use K911\Swoole\Tests\Unit\Server\SwooleServerMock\SwooleServerMockSwoole4;

final class SwooleServerMockFactory
{
    public static function make(bool $taskworker = false): SwooleServerMock
    {
        $extension = '';

        if (extension_loaded('openswoole')) {
            $extension = 'openswoole';

            if (str_starts_with(swoole_version(), '4.')) {
                require_once __DIR__.'/SwooleServerMock/SwooleServerMockOpenSwoole4.php';

                return SwooleServerMockOpenSwoole4::make($taskworker);
            }
        } elseif (extension_loaded('swoole')) {
            $extension = 'swoole';

            if (str_starts_with(swoole_version(), '4.')) {
                require __DIR__.'/SwooleServerMock/SwooleServerMockSwoole4.php';

                return SwooleServerMockSwoole4::make($taskworker);
            }
        }

        throw new \RuntimeException(\sprintf('Unsupported Swoole version %s for extension %s.', swoole_version(), $extension));
    }
}
