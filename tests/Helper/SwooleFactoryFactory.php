<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Helper;

use ArrayObject;
use SwooleBundle\SwooleBundle\Bridge\OpenSwoole\OpenSwooleFactory;
use SwooleBundle\SwooleBundle\Bridge\Swoole\SwooleFactory;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Common\Adapter\SystemSwooleFactory;
use SwooleBundle\SwooleBundle\Common\System\Extension;
use SwooleBundle\SwooleBundle\Common\System\System;

final class SwooleFactoryFactory
{
    private static ?SystemSwooleFactory $systemSwooleFactory = null;

    public static function newInstance(): Swoole
    {
        return self::getSystemSwooleFactory()->newInstance();
    }

    private static function getSystemSwooleFactory(): SystemSwooleFactory
    {
        if (self::$systemSwooleFactory === null) {
            self::$systemSwooleFactory = new SystemSwooleFactory(
                System::create(),
                new ArrayObject([
                    Extension::SWOOLE => new SwooleFactory(),
                    Extension::OPENSWOOLE => new OpenSwooleFactory(),
                ]),
            );
        }

        return self::$systemSwooleFactory;
    }
}
