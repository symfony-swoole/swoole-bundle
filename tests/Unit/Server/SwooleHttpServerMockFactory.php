<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Server;

use K911\Swoole\Common\System\System;
use K911\Swoole\Tests\Unit\Server\SwooleHttpServerMock\SwooleHttpServerMockOpenSwoole22;
use K911\Swoole\Tests\Unit\Server\SwooleHttpServerMock\SwooleHttpServerMockSwoole5;

final class SwooleHttpServerMockFactory
{
    public static function make(): SwooleHttpServerMock
    {
        $system = System::create();

        if ($system->extension()->isOpenswoole()) {
            if (str_starts_with($system->version()->toString(), '22.')) {
                return SwooleHttpServerMockOpenSwoole22::make();
            }
        } elseif ($system->extension()->isSwoole()) {
            if (str_starts_with($system->version()->toString(), '5.')) {
                return SwooleHttpServerMockSwoole5::make();
            }
        }

        throw new \RuntimeException(\sprintf('Unsupported Swoole version %s for extension %s.', $system->version()->toString(), $system->extension()->toString()));
    }
}
