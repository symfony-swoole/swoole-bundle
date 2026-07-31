<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component\Concurrency;

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
 */
final class SerialQueue
{
    public const int DEFAULT_CAPACITY = 1024;

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
     */
    public function __construct(
        private readonly Swoole $swoole,
        private readonly Closure $consumer,
        int $capacity = self::DEFAULT_CAPACITY,
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
            while (!$this->channel->isEmpty()) {
                $payload = $this->channel->pop();

                if ($payload === false) {
                    break;
                }

                $this->consume($payload);
            }
        } finally {
            $this->consumerContextId = null;
            $this->consuming = false;
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

        --$this->queued;

        try {
            ($this->consumer)($payload);
        } catch (Throwable $throwable) {
            error_log(sprintf('Serially queued work failed: %s', $throwable->getMessage()));
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
