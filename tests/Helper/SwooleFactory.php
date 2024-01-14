<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Helper;

use SwooleBundle\SwooleBundle\Bridge\OpenSwoole\OpenSwooleFactory;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Common\Adapter\SystemSwooleFactory;
use SwooleBundle\SwooleBundle\Common\System\Extension;
use SwooleBundle\SwooleBundle\Common\System\System;

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
                    Extension::SWOOLE => new \SwooleBundle\SwooleBundle\Bridge\Swoole\SwooleFactory(),
                    Extension::OPENSWOOLE => new OpenSwooleFactory(),
                ]),
            );
        }

        return self::$systemSwooleFactory;
    }
}
