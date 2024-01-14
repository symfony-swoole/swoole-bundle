<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Swoole;

use Swoole\Coroutine\WaitGroup as SwooleWaitGroup;
use SwooleBundle\SwooleBundle\Common\Adapter\WaitGroup as CommonWaitGroup;

final class WaitGroup extends SwooleWaitGroup implements CommonWaitGroup
{
    public function __construct(int $delta = 0)
    {
        parent::__construct($delta);
    }
}
