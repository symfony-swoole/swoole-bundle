<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Container\ServicePool;

use RuntimeException;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Resetter;

/**
 * A resetter that fails, standing in for the ConcurrencyException a real one raised from
 * Twig\Environment::resetGlobals() - the one that used to take the worker with it.
 */
final readonly class ThrowingResetter implements Resetter
{
    public function __construct(
        private string $message = 'reset blew up',
    ) {}

    public function reset(object $service): void
    {
        throw new RuntimeException($this->message);
    }
}
