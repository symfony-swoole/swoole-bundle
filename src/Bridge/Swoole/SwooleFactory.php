<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Swoole;

use K911\Swoole\Common\Adapter\SwooleFactory as CommonSwooleAdapterFactory;

final class SwooleFactory implements CommonSwooleAdapterFactory
{
    public function newInstance(): Swoole
    {
        return new Swoole();
    }
}
