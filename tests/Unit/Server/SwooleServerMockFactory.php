<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Server;

final class SwooleServerMockFactory
{
    public static function make(bool $taskworker = false): SwooleServerMock
    {
        if (str_starts_with(swoole_version(), '4.') || str_starts_with(swoole_version(), '5.')) {
            return SwooleServerMock::make($taskworker);
        }

        $extension = '';

        if (extension_loaded('openswoole')) {
            $extension = 'openswoole';
        } elseif (extension_loaded('swoole')) {
            $extension = 'swoole';
        }

        throw new \RuntimeException(\sprintf('Unsupported Swoole version %s for extension %s.', swoole_version(), $extension));
    }
}
