<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component\Concurrency;

use WeakReference;

/**
 * Lets the pinned consumers of {@see SerialQueue}s keep what they hold while their queues are idle, for as long
 * as something is there to make them let go.
 *
 * On its own a pinned consumer releases what it holds once its queue has been idle for a moment and leaves,
 * because nothing else would ever end it: a scheduler with nothing left to run but parked consumers never
 * finishes, and a worker on its way out waits for its coroutines. A server worker has that something else - its
 * exit - so while it runs, its consumers can keep their resource: a log file stays open from one request to the
 * next, however long the pause between them, instead of being opened again by a new consumer for the first line
 * after every pause.
 *
 * Retained from a worker's start until it begins to exit (both are tagged as listeners of the worker's events),
 * and nowhere else: a console command, or a test running coroutines of its own, keeps consumers that let go
 * when idle. Releasing wakes every consumer waiting on its queue, so it lets go and leaves right away rather
 * than when its idle timeout next comes round.
 */
final class ConsumerRetention
{
    private bool $retaining = false;

    /**
     * The queues whose consumers have been pinned so far, by object id. Weak, as a queue is only of interest
     * while something still holds it - a consumer holds its own for as long as it runs.
     *
     * @var array<int, WeakReference<SerialQueue>>
     */
    private array $queues = [];

    public function retain(): void
    {
        $this->retaining = true;
    }

    public function release(): void
    {
        $this->retaining = false;

        foreach ($this->queues as $id => $reference) {
            $queue = $reference->get();

            if ($queue === null) {
                unset($this->queues[$id]);

                continue;
            }

            $queue->wakeIdleConsumer();
        }
    }

    public function isRetaining(): bool
    {
        return $this->retaining;
    }

    /**
     * Called by a queue as its pinned consumer starts, so that releasing can reach it.
     */
    public function track(SerialQueue $queue): void
    {
        $this->queues[spl_object_id($queue)] = WeakReference::create($queue);
    }
}
