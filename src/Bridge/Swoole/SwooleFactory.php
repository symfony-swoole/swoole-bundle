<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Swoole;

use SwooleBundle\SwooleBundle\Common\Adapter\Swoole as CommonSwoole;
use SwooleBundle\SwooleBundle\Common\Adapter\SwooleFactory as CommonSwooleAdapterFactory;

final class SwooleFactory implements CommonSwooleAdapterFactory
{
    public function newInstance(): CommonSwoole
    {
        return new Swoole();
    }
}
