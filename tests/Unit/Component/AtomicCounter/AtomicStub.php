<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Component\AtomicCounter;

use Swoole\Atomic;

final class AtomicStub extends Atomic
{
    public function __construct(private readonly int $value)
    {
        parent::__construct(0);
    }

    public function get(): int
    {
        return $this->value;
    }
}
