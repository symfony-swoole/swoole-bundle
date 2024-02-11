<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server;

use RuntimeException;
use SwooleBundle\SwooleBundle\Common\System\System;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMock\SwooleHttpServerMockOpenSwoole22;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMock\SwooleHttpServerMockSwoole5;

final class SwooleHttpServerMockFactory
{
    public static function make(): SwooleHttpServerMock
    {
        $system = System::create();

        if ($system->extension()->isOpenswoole() && str_starts_with($system->version()->toString(), '22.')) {
            return SwooleHttpServerMockOpenSwoole22::make();
        }

        if ($system->extension()->isSwoole() && str_starts_with($system->version()->toString(), '5.')) {
            return SwooleHttpServerMockSwoole5::make();
        }

        throw new RuntimeException(
            sprintf(
                'Unsupported Swoole version %s for extension %s.',
                $system->version()->toString(),
                $system->extension()->toString()
            )
        );
    }
}
