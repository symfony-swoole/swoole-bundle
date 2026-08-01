<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Monolog;

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
     * Nothing queued may outlive the stream it is meant to be written to.
     */
    public function close(): void
    {
        $this->writeQueue?->drain();

        parent::close();
    }

    public function reset(): void
    {
        $this->writeQueue?->drain();

        parent::reset();
    }

    protected function write(LogRecord $record): void
    {
        $this->writeQueue()->submit($record);
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
        );
    }
}
