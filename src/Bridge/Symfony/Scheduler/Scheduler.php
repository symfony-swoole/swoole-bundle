<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Scheduler;

/**
 * Polls every registered {@see \Symfony\Component\Scheduler\ScheduleProviderInterface}'s due
 * messages and dispatches them through the message bus. See {@see DefaultScheduler} for the
 * bundle's own implementation, and {@see WithScheduler} for what drives {@see run()} repeatedly.
 *
 * To run something else instead - e.g. an app-specific database connection health check before
 * schedules are polled for due messages - register a service of your own under this interface's
 * id in place of the bundle's own, the same way {@see \SwooleBundle\SwooleBundle\Server\HttpServerConfiguration}
 * is swapped for a custom implementation.
 */
interface Scheduler
{
    public function run(): void;
}
