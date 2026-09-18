<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Container\Proxy;

/**
 * The same role, not readonly - what every proxy was generated for before readonly ones existed.
 */
// phpcs:ignore SlevomatCodingStandard.Classes.RequireAbstractOrFinal
class MutableHandleHolder
{
    private int $calls = 0;

    public function handleId(): int
    {
        $this->calls++;

        return $this->calls;
    }
}
