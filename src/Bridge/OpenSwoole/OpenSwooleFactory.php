<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\OpenSwoole;

use K911\Swoole\Common\Adapter\Swoole;
use K911\Swoole\Common\Adapter\SwooleFactory;

final class OpenSwooleFactory implements SwooleFactory
{
    public function newInstance(): Swoole
    {
        return new OpenSwoole();
    }
}
