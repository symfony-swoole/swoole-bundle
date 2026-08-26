<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Container\Proxy;

use stdClass;

/**
 * The shape an unmanaged factory has to have to be wrapped: a class with the method named in its tag.
 *
 * Never called - what is under test happens while the factory is being wrapped, before anything asks it
 * to build anything.
 *
 * Deliberately not final, which is what the sniff below would otherwise ask for: wrapping a factory
 * means generating a class that extends it, and a final one has nothing to extend. Production code gets
 * around that with the class modifier, which un-finals the class at runtime; a plain unit test has no
 * such thing running.
 */
// phpcs:ignore SlevomatCodingStandard.Classes.RequireAbstractOrFinal.ClassNeitherAbstractNorFinal
class StubUnmanagedFactory
{
    public function create(): stdClass
    {
        return new stdClass();
    }
}
