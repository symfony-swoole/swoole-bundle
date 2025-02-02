<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server;

use Closure;
use ReflectionFunction;

trait SameClosureAssertion
{
    public static function assertSameClosure(Closure $closure1, Closure $closure2): void
    {
        $reflection1 = new ReflectionFunction($closure1);
        $reflection2 = new ReflectionFunction($closure2);

        // Check if both closures are bound to an object
        self::assertSame($reflection1->getClosureThis(), $reflection2->getClosureThis());
        self::assertSame($reflection1->getName(), $reflection2->getName());
    }
}
