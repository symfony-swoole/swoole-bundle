<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Helper;

use stdClass;
use Swoole\Coroutine\Channel;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePool;

/**
 * A pool whose instance, given back, waits a moment on the way - as a connection's ping does.
 *
 * @template-implements ServicePool<stdClass>
 */
final readonly class WaitingOnTheWayBackServicePool implements ServicePool
{
    public function __construct(
        private float $waitSeconds = 0.01,
    ) {}

    public function get(): object
    {
        return new stdClass();
    }

    public function getAssigned(): object
    {
        return new stdClass();
    }

    public function releaseFromCoroutine(?int $coroutineId = null): void
    {
        (new Channel(1))->pop($this->waitSeconds);
    }

    public function discardUnstableAssigned(): void
    {
        // nothing is ever unstable here
    }

    public function drain(): void
    {
        // nothing is kept here
    }
}
