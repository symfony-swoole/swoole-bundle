<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Monolog;

use Closure;
use Monolog\LogRecord;
use SwooleBundle\SwooleBundle\Bridge\CommonSwoole\SystemSwooleFactory;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Component\Concurrency\SerialQueue;

/**
 * Makes a Monolog stream based handler safe to share between coroutines.
 *
 * Monolog handlers are registered as shared services, so a single instance is used by every coroutine of a
 * worker. Its stream handling is not built for that: the resource is opened with a check-then-open which
 * two coroutines can pass at the same time, and the state used to detect write failures and log rotation
 * is overwritten across the yields happening inside fopen()/fwrite().
 *
 * Instead of locking around all of that, every write is handed to a {@see SerialQueue}, which replays them
 * one at a time in a single consumer coroutine. Only one coroutine is ever inside the original write(), so
 * none of that state can be observed by anyone else and the upstream implementation is used as it is.
 *
 * The stream is opened by that consumer, which is why the queue's consumer is pinned and why closing and
 * resetting are handed over to it as well instead of being done where they are asked for. Monolog closes
 * the file on reset - deliberately, so an externally rotated file is picked up - and these handlers are
 * reset once per request, from whichever coroutine happens to be releasing the pooled instance. Closing a
 * file from a coroutine other than the one that opened it is the exact violation this trait exists to avoid.
 *
 * As a side effect logging stops blocking the request coroutine on disk IO.
 */
trait CoroutineSafeWrites
{
    /**
     * Enough headroom for bursts of logging while still applying backpressure instead of growing forever.
     */
    private const int WRITE_QUEUE_CAPACITY = 4096;

    private SerialQueue|null $writeQueue = null;

    private Swoole|null $swoole = null;

    public function setSwoole(Swoole $swoole): void
    {
        $this->swoole = $swoole;
    }

    /**
     * Nothing queued may outlive the stream it is meant to be written to - which the queue takes care of by
     * itself here, since the close is queued up behind everything submitted before it.
     */
    public function close(): void
    {
        $this->inWriteQueue(function (): void {
            parent::close();
        });
    }

    public function reset(): void
    {
        $this->inWriteQueue(function (): void {
            parent::reset();
        });
    }

    /**
     * Queues the line, and deliberately not the record that produced it.
     *
     * Monolog formats before it writes - `AbstractProcessingHandler::handle()` sets `$record->formatted`
     * and only then calls this - so by here the bytes already exist and the record is of no further use
     * to a stream handler, which reads nothing but `formatted`.
     *
     * That distinction is the whole of this method. A payload is held by the consumer until it pops the
     * next one, so whatever a record still references is finally released **in the consumer's coroutine**
     * rather than in the one that logged - and released means destructed. A record carries far more than
     * it looks: anything logged with an exception in its context carries that exception's trace, and PHP
     * keeps every frame's *arguments* unless `zend.exception_ignore_args` is on. One failed HTTP call is
     * enough to put a live response in there, whose destructor then tears down its buffer stream and its
     * connection's DNS cache from a coroutine that never made the request. With fiber context checking on
     * that is not a warning, it is the worker gone - measured at twenty-eight dead workers in a single
     * end-to-end run, from calls that had merely failed.
     *
     * Stripping the context and the extra leaves a record of scalars, an enum and a DateTimeImmutable:
     * nothing whose destruction anybody can observe. It cannot go stale either, since the formatter has
     * already read them.
     *
     * Ownership release is the other tool for handing an object between coroutines, and it cannot reach
     * this: it works from a declared class and property, and a value sitting in an exception's trace
     * arguments is on no property path at all.
     */
    protected function write(LogRecord $record): void
    {
        $this->writeQueue()->submit($record->with(
            context: [],
            extra: [],
            formatted: $record->formatted,
        ));
    }

    /**
     * Nothing has been written yet while there is no queue, so there is no stream anybody could own and the
     * task can be done right here.
     *
     * @param Closure(): void $task
     */
    private function inWriteQueue(Closure $task): void
    {
        if ($this->writeQueue === null) {
            $task();

            return;
        }

        $this->writeQueue->runInConsumer($task);
    }

    private function writeQueue(): SerialQueue
    {
        return $this->writeQueue ??= new SerialQueue(
            $this->swoole ?? SystemSwooleFactory::newFactoryInstance()->newInstance(),
            function (mixed $record): void {
                if (!$record instanceof LogRecord) {
                    return;
                }

                parent::write($record);
            },
            self::WRITE_QUEUE_CAPACITY,
            // the stream belongs to the consumer coroutine, so it cannot be left open for the next one
            function (): void {
                parent::close();
            },
        );
    }
}
