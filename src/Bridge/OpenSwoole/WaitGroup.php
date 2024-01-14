<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\OpenSwoole;

use OpenSwoole\Core\Coroutine\WaitGroup as OpenSwooleWaitGroup;
use SwooleBundle\SwooleBundle\Common\Adapter\WaitGroup as CommonWaitGroup;

final class WaitGroup extends OpenSwooleWaitGroup implements CommonWaitGroup
{
    public function __construct(int $delta = 0)
    {
        parent::__construct($delta);
    }
}
