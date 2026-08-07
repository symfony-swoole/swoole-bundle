<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component\Concurrency;

use Closure;
use Swoole\Coroutine\Channel;

/**
 * Sentinel carrying work that has to happen in the consumer coroutine rather than in the one asking for it,
 * because it touches the same resource the consumer callback does.
 *
 * Queued like any other payload, so it runs after everything submitted before it, and acknowledged once it
 * is done, so the coroutine which pushed it can wait for the effect it asked for.
 *
 * @internal
 */
final readonly class TaskMarker
{
    /**
     * @param Closure(): void $task
     */
    public function __construct(
        private Closure $task,
        private Channel $acknowledgement,
    ) {}

    public function run(): void
    {
        ($this->task)();
    }

    public function acknowledge(): void
    {
        $this->acknowledgement->push(true);
    }
}
