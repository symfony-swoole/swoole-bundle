<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Swoole;

use SwooleBundle\SwooleBundle\Common\Adapter\SwooleFactory as CommonSwooleAdapterFactory;

final class SwooleFactory implements CommonSwooleAdapterFactory
{
    public function newInstance(): Swoole
    {
        return new Swoole();
    }
}
