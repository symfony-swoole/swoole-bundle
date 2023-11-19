<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Server;

use K911\Swoole\Tests\Unit\Server\SwooleHttpServerMock\SwooleHttpServerMockOpenSwoole4;
use K911\Swoole\Tests\Unit\Server\SwooleHttpServerMock\SwooleHttpServerMockSwoole5;

final class SwooleHttpServerMockFactory
{
    public static function make(): SwooleHttpServerMock
    {
        $extension = '';

        if (extension_loaded('openswoole')) {
            $extension = 'openswoole';

            if (str_starts_with(swoole_version(), '4.')) {
                return SwooleHttpServerMockOpenSwoole4::make();
            }
        } elseif (extension_loaded('swoole')) {
            $extension = 'swoole';

            if (str_starts_with(swoole_version(), '5.')) {
                return SwooleHttpServerMockSwoole5::make();
            }
        }

        throw new \RuntimeException(\sprintf('Unsupported Swoole version %s for extension %s.', swoole_version(), $extension));
    }
}
