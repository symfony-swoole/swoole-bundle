<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Component\AtomicCounter;

use Swoole\Atomic;

final class AtomicSpy extends Atomic
{
    private bool $incremented = false;

    public function __construct()
    {
        parent::__construct(0);
    }

    public function add(?int $value = null): int
    {
        $this->incremented = $value === 1;

        return $this->incremented ? 1 : 0;
    }

    public function getIncremented(): bool
    {
        return $this->incremented;
    }
}
