<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component\Concurrency;

use Swoole\Coroutine\Channel;

/**
 * Sentinel pushed into a {@see SerialQueue} by a coroutine waiting for everything queued before it to be
 * consumed. Because the queue is FIFO, the acknowledgement can only be sent once all preceding payloads
 * have been processed.
 *
 * @internal
 */
final readonly class DrainMarker
{
    public function __construct(
        private Channel $acknowledgement,
    ) {}

    public function acknowledge(): void
    {
        $this->acknowledgement->push(true);
    }
}
