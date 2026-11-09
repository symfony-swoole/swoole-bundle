<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Common\Adapter;

/**
 * A runtime that can let the other coroutines run without a timer.
 *
 * Kept apart from Swoole so that an adapter implemented elsewhere - a decorator of the bundle's own, say - does not
 * break: what asks for it checks for it, and does without when it is not there.
 */
interface CoroutineYielding
{
    /**
     * Lets every coroutine that is ready to run go once, then carries on with this one.
     *
     * Nothing is started for it - no coroutine and no timer - so a limit on how many coroutines run at once
     * (OpenSwoole's max_concurrency) has nothing to queue.
     */
    public function yieldToOtherCoroutines(): void;
}
