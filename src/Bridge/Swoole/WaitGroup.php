<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Swoole;

use K911\Swoole\Common\Adapter\WaitGroup as CommonWaitGroup;
use Swoole\Coroutine\WaitGroup as SwooleWaitGroup;

final class WaitGroup extends SwooleWaitGroup implements CommonWaitGroup
{
    public function __construct(int $delta = 0)
    {
        parent::__construct($delta);
    }
}
