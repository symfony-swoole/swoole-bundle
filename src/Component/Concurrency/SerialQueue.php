<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component\Concurrency;

use Assert\Assertion;
use Closure;
use Swoole\Coroutine\Channel;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use Throwable;

/**
 * Runs work submitted from arbitrarily many coroutines through a single consumer, one payload at a time,
 * in submission order - without ever taking a lock.
 *
 * Producers only push into a channel and return, the actual work happens in one consumer coroutine which
 * owns whatever resource the work touches. Since only one coroutine is ever inside the consumer callback,
 * state guarded by this queue does not need any synchronization at all.
 *
 * The consumer is started lazily and exits once the queue runs dry, a later submission starts a new one.
 * That handshake is race free: neither the emptiness check nor the flag reset below contain a yield point,
 * so a producer which sees `$consuming === true` after its push is guaranteed that the consumer has not
 * made its final emptiness check yet, and will therefore pick the payload up.
 *
 * A queue can also be given something for its consumer to release, which is what a resource outliving a
 * single payload needs: a file opened by one consumer and closed by the next is opened and closed by two
 * different coroutines, which is precisely what this queue exists to keep from happening. Such a consumer
 * waits on the channel rather than leaving the moment it runs dry, so the resource stays with the coroutine
 * that acquired it, and releases it before giving up on an idle queue. Work touching it is handed over with
 * {@see runInConsumer} instead of being done by the coroutine asking for it.
 */
final class SerialQueue
{
    public const int DEFAULT_CAPACITY = 1024;

    /**
     * How long a consumer holding a resource waits on an empty queue before letting go of it and leaving.
     *
     * Waiting forever would be simpler but would also keep the coroutine alive for as long as the queue
     * exists, and a scheduler with nothing left to run but parked consumers never finishes.
     */
    public const float DEFAULT_IDLE_TIMEOUT_SECONDS = 1.0;

    /**
     * Coroutine id reported when running outside of a coroutine.
     */
    private const int NO_COROUTINE_CONTEXT_ID = -1;

    /**
     * Upper bound for {@see drain()} waiting on a consumer running in another coroutine. Only ever reached
     * when that consumer died without acknowledging, in which case waiting forever would be worse.
     */
    private const float DRAIN_TIMEOUT_SECONDS = 5.0;

    private readonly Channel $channel;

    private bool $consuming = false;

    private int|null $consumerContextId = null;

    /**
     * Kept next to the channel because a channel cannot be inspected from outside of a coroutine, and the
     * shutdown path where the remaining payloads matter runs exactly there.
     */
    private int $queued = 0;

    /**
     * @param Closure(mixed): void $consumer invoked for every submitted payload, never concurrently
     * @param int $capacity how many payloads may be queued before producers start waiting for the consumer
     * @param Closure(): void|null $consumerRelease lets go of whatever the consumer acquired while working.
     *     Giving one pins the consumer: it waits on an empty queue instead of leaving it to the next
     *     coroutine, and releases here before it does leave.
     * @param float $idleTimeout how long a pinned consumer waits on an empty queue before releasing
     */
    public function __construct(
        private readonly Swoole $swoole,
        private readonly Closure $consumer,
        int $capacity = self::DEFAULT_CAPACITY,
        private readonly Closure|null $consumerRelease = null,
        private readonly float $idleTimeout = self::DEFAULT_IDLE_TIMEOUT_SECONDS,
    ) {
        $this->channel = new Channel($capacity);
    }

    /**
     * Hands a payload over to the consumer. Returns as soon as it is queued, the work itself happens in
     * the consumer coroutine.
     */
    public function submit(mixed $payload): void
    {
        ++$this->queued;

        if ($this->swoole->getCoroutineId() === self::NO_COROUTINE_CONTEXT_ID) {
            // A channel can only be used from within a coroutine, and without a scheduler nothing can
            // interleave anyway, so the payload is consumed right where it was submitted.
            $this->consumeDirectly($payload);

            return;
        }

        $this->channel->push($payload);
        $this->startConsumer();
    }

    /**
     * Runs a task in the consumer coroutine, behind everything submitted before it, and waits for it to be
     * done. Only meaningful with a pinned consumer, since the point is reaching the coroutine which owns
     * whatever the task touches, and without pinning there is no such single coroutine.
     *
     * Called from the consumer itself the task simply runs - it is already in the right place, and queueing
     * it up behind the payload currently being consumed would wait for something that cannot happen until
     * this call returns. Monolog does exactly that: resetting a stream handler closes it.
     *
     * @param Closure(): void $task
     */
    public function runInConsumer(Closure $task): void
    {
        Assertion::true(
            $this->isConsumerPinned(),
            'Handing work over to the consumer only makes sense when the consumer is pinned.',
        );

        $contextId = $this->swoole->getCoroutineId();

        if ($this->consumerContextId !== null && $this->consumerContextId === $contextId) {
            $task();

            return;
        }

        // No consumer means nothing has been consumed yet, or that the last one already released what it
        // held and left. Either way there is nobody to hand the task to, and nothing it could interfere with.
        if ($contextId === self::NO_COROUTINE_CONTEXT_ID || !$this->consuming) {
            $task();

            return;
        }

        $acknowledgement = new Channel(1);
        $this->channel->push(new TaskMarker($task, $acknowledgement));
        $acknowledgement->pop(self::DRAIN_TIMEOUT_SECONDS);
    }

    /**
     * Blocks until everything queued so far has been consumed. Meant for shutdown paths which have to make
     * sure nothing is lost before the underlying resource goes away.
     *
     * Calling this from within the consumer callback is a no-op - the payloads are being processed already.
     */
    public function drain(): void
    {
        $contextId = $this->swoole->getCoroutineId();

        if ($this->consumerContextId !== null && $this->consumerContextId === $contextId) {
            return;
        }

        if ($contextId === self::NO_COROUTINE_CONTEXT_ID) {
            // Nothing can be popped off a channel from here. Anything still queued was orphaned by a
            // scheduler which stopped before its consumer was done, and cannot be recovered.
            $this->reportOrphanedPayloads();

            return;
        }

        if (!$this->consuming) {
            if ($this->isConsumerPinned()) {
                // Consuming right here would touch the resource from the wrong coroutine, which is the one
                // thing a pinned consumer is for. There is nothing waiting either - a consumer only leaves
                // an empty queue behind.
                return;
            }

            $this->consumeQueued();

            return;
        }

        // Somebody else is consuming - queue up behind everything already submitted and wait for it.
        $acknowledgement = new Channel(1);
        $this->channel->push(new DrainMarker($acknowledgement));
        $acknowledgement->pop(self::DRAIN_TIMEOUT_SECONDS);
    }

    public function isEmpty(): bool
    {
        return $this->queued === 0;
    }

    private function startConsumer(): void
    {
        if (!$this->claim()) {
            return;
        }

        go(function (): void {
            $this->consumeClaimed();
        });
    }

    /**
     * Consumes everything queued in the calling coroutine, unless a consumer is running already.
     */
    private function consumeQueued(): void
    {
        if (!$this->claim()) {
            return;
        }

        $this->consumeClaimed();
    }

    /**
     * Marks the queue as being consumed. The claim is taken before the consumer coroutine is even started,
     * so that no second consumer can be started while it is being spawned.
     */
    private function claim(): bool
    {
        if ($this->consuming) {
            return false;
        }

        $this->consuming = true;

        return true;
    }

    /**
     * Consumes a payload in the calling context, used when there is no coroutine to queue it up for.
     */
    private function consumeDirectly(mixed $payload): void
    {
        $this->consumerContextId = self::NO_COROUTINE_CONTEXT_ID;

        try {
            $this->consume($payload);
        } finally {
            $this->consumerContextId = null;
        }
    }

    private function consumeClaimed(): void
    {
        $this->consumerContextId = $this->swoole->getCoroutineId();

        try {
            if ($this->isConsumerPinned()) {
                $this->consumeUntilIdle();
            } else {
                $this->consumeUntilEmpty();
            }
        } finally {
            $this->consumerContextId = null;
            $this->consuming = false;
        }
    }

    private function consumeUntilEmpty(): void
    {
        while (!$this->channel->isEmpty()) {
            $payload = $this->channel->pop();

            if ($payload === false) {
                break;
            }

            $this->consume($payload);
        }
    }

    /**
     * Waits on the queue rather than handing it to whoever submits next, so that everything touching the
     * resource this consumer holds happens in this one coroutine.
     *
     * Releasing is done before the emptiness check rather than after the loop, because it is the one thing
     * here that can yield - closing a file does - and a producer pushing during it would be left with a
     * claim nobody is honouring. Seeing something queued afterwards means going back to work, on a resource
     * that will be acquired again the next time it is needed.
     */
    private function consumeUntilIdle(): void
    {
        while (true) {
            $payload = $this->channel->pop($this->idleTimeout);

            if ($payload !== false) {
                $this->consume($payload);

                continue;
            }

            $this->releaseConsumer();

            if ($this->channel->isEmpty()) {
                return;
            }
        }
    }

    /**
     * A failing payload must never take the consumer down with it, otherwise everything queued behind it
     * would be stuck until the next submission.
     */
    private function consume(mixed $payload): void
    {
        if ($payload instanceof DrainMarker) {
            $payload->acknowledge();

            return;
        }

        if ($payload instanceof TaskMarker) {
            $this->runHandedOverTask($payload);

            return;
        }

        --$this->queued;

        try {
            ($this->consumer)($payload);
        } catch (Throwable $throwable) {
            error_log(sprintf('Serially queued work failed: %s', $throwable->getMessage()));
        }
    }

    private function isConsumerPinned(): bool
    {
        return $this->consumerRelease !== null;
    }

    /**
     * Runs while the consumer context id is still set, so that anything the release does through
     * {@see runInConsumer} recognises it is already in the right coroutine instead of queueing up behind
     * itself.
     */
    private function releaseConsumer(): void
    {
        if ($this->consumerRelease === null) {
            return;
        }

        try {
            ($this->consumerRelease)();
        } catch (Throwable $throwable) {
            error_log(sprintf('Releasing a serial queue consumer failed: %s', $throwable->getMessage()));
        }
    }

    /**
     * The coroutine waiting for this task has to be woken up whatever the task did, otherwise it stays on
     * its acknowledgement until the drain timeout for no reason.
     */
    private function runHandedOverTask(TaskMarker $task): void
    {
        try {
            $task->run();
        } catch (Throwable $throwable) {
            error_log(sprintf('Serially queued task failed: %s', $throwable->getMessage()));
        } finally {
            $task->acknowledge();
        }
    }

    private function reportOrphanedPayloads(): void
    {
        if ($this->queued <= 0) {
            return;
        }

        error_log(
            sprintf('Dropped %d serially queued payloads, no coroutine left to consume them.', $this->queued),
        );
    }
}
