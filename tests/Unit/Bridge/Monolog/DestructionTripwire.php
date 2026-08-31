<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Monolog;

/**
 * Records which coroutine finally let go of it.
 *
 * Stands in for anything a log record can end up carrying without being asked to - an exception's trace
 * arguments are the way it usually happens - and answers the one question that matters about the write
 * queue: whether what a record referenced is released where it was logged, or where it was written.
 */
final readonly class DestructionTripwire
{
    public function __construct(private DestructionSite $site) {}

    public function __destruct()
    {
        $this->site->noteTheCoroutineReleasingHere();
    }
}
