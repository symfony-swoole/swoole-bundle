<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Container\ServicePool;

use RuntimeException;
use stdClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePool;

/**
 * A pool whose release blows up, standing in for whatever a real one can throw on the way out.
 *
 * @template-implements ServicePool<object>
 */
final readonly class ThrowingServicePool implements ServicePool
{
    public function __construct(
        private object $assigned = new stdClass(),
        private string $message = 'release blew up',
    ) {}

    public function get(): object
    {
        return $this->assigned;
    }

    public function getAssigned(): object
    {
        return $this->assigned;
    }

    public function releaseFromCoroutine(): void
    {
        throw new RuntimeException($this->message);
    }
}
