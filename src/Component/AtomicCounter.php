<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component;

use Swoole\Atomic;

final class AtomicCounter
{
    public function __construct(private readonly Atomic $counter) {}

    public function increment(): void
    {
        $this->counter->add(1);
    }

    public function get(): int
    {
        return $this->counter->get();
    }

    public static function fromZero(): self
    {
        return new self(new Atomic(0));
    }
}
