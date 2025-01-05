<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Helper;

use SwooleBundle\SwooleBundle\Bridge\CommonSwoole\SystemSwooleFactory;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;

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
            self::$systemSwooleFactory = SystemSwooleFactory::newFactoryInstance();
        }

        return self::$systemSwooleFactory;
    }
}
