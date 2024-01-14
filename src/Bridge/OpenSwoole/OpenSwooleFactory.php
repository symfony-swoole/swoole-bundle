<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\OpenSwoole;

use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Common\Adapter\SwooleFactory;

final class OpenSwooleFactory implements SwooleFactory
{
    public function newInstance(): Swoole
    {
        return new OpenSwoole();
    }
}
