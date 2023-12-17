<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\OpenSwoole;

use K911\Swoole\Common\Adapter\WaitGroup as CommonWaitGroup;
use OpenSwoole\Core\Coroutine\WaitGroup as OpenSwooleWaitGroup;

final class WaitGroup extends OpenSwooleWaitGroup implements CommonWaitGroup
{
    public function __construct(int $delta = 0)
    {
        parent::__construct($delta);
    }
}
