<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Container\Proxy;

use stdClass;

/**
 * A readonly class holding something mutable - a stand-in for a client holding a connection handle.
 *
 * Not final, because removing `final` from a proxied class is z-engine's job, and z-engine is not loaded
 * in unit tests.
 */
// phpcs:ignore SlevomatCodingStandard.Classes.RequireAbstractOrFinal
readonly class ReadonlyHandleHolder
{
    public function __construct(
        public string $name,
        private stdClass $handle = new stdClass(),
    ) {}

    public function handleId(): int
    {
        return spl_object_id($this->handle);
    }
}
