<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Helper;

use K911\Swoole\Bridge\OpenSwoole\OpenSwooleFactory;
use K911\Swoole\Common\Adapter\Swoole;
use K911\Swoole\Common\Adapter\SystemSwooleFactory;
use K911\Swoole\Common\System\Extension;
use K911\Swoole\Common\System\System;

final class SwooleFactory
{
    private static ?SystemSwooleFactory $systemSwooleFactory = null;

    public static function newInstance(): Swoole
    {
        return self::getSystemSwooleFactory()->newInstance();
    }

    private static function getSystemSwooleFactory(): SystemSwooleFactory
    {
        if (null === self::$systemSwooleFactory) {
            self::$systemSwooleFactory = new SystemSwooleFactory(
                System::create(),
                new \ArrayObject([
                    Extension::SWOOLE => new \K911\Swoole\Bridge\Swoole\SwooleFactory(),
                    Extension::OPENSWOOLE => new OpenSwooleFactory(),
                ]),
            );
        }

        return self::$systemSwooleFactory;
    }
}
