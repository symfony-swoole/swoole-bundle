<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool;

use Throwable;

final class ServicePoolContainer
{
    /**
     * @var array<int, ServicePoolEntry<object>>
     */
    private array $poolEntries = [];

    /**
     * @var array<int, array<int, ServicePoolEntry<object>>>
     */
    private array $poolEntriesToReset = [];

    /**
     * @param array<int, ServicePoolEntry<object>> $poolEntries
     */
    public function __construct(array $poolEntries)
    {
        foreach ($poolEntries as $poolEntry) {
            $this->addPoolEntry($poolEntry);
        }
    }

    /**
     * @param ServicePoolEntry<object> $poolEntry
     */
    public function addPoolEntry(ServicePoolEntry $poolEntry): void
    {
        $this->poolEntries[] = $poolEntry;

        if ($poolEntry->resetter === null) {
            return;
        }

        if (!isset($this->poolEntriesToReset[$poolEntry->resetPriority])) {
            $this->poolEntriesToReset[$poolEntry->resetPriority] = [];
        }

        $this->poolEntriesToReset[$poolEntry->resetPriority][] = $poolEntry;
    }

    /**
     * Nothing here may throw.
     *
     * This runs from a Co::defer() callback registered by CoWrapper::defer(), which is to say from the
     * very end of a coroutine, where there is no caller left to catch anything. A throwable
     * escaping from here is an uncaught fatal that takes the whole worker down with it - one request
     * resetting one service badly, and every other request in flight on that worker dies with it:
     *
     *   ERROR php_openswoole_server_rshutdown(): Fatal error: Uncaught Exception:
     *   ... in SimpleResetter->reset(Object(Twig\Environment))
     *   WARNING Server::check_worker_exit_status(): worker(pid=190, id=1) abnormal exit, status=255
     *
     * So every step is guarded individually and the cycle carries on: one service failing to reset must
     * not stop the rest from being reset, and above all must not stop them being released - a coroutine
     * that dies still holding its pool entries takes those instances out of circulation for good, and
     * enough of those exhaust the pool and hang the worker instead of killing it.
     */
    public function releaseFromCoroutine(): void
    {
        // the reset cycle has to be executed before releasing the services back to the pool
        // to not get assigned too early to other coroutines which can cause deadlocks
        // during the reset cycle if there are dependencies among released and not released services
        foreach ($this->poolEntriesToReset as $prioritizedPoolEntries) {
            foreach ($prioritizedPoolEntries as $poolEntry) {
                if ($poolEntry->resetter === null) {
                    continue;
                }

                $instance = $poolEntry->pool->getAssigned();

                if ($instance === null) {
                    continue;
                }

                try {
                    $poolEntry->resetter->reset($instance);
                } catch (Throwable $throwable) {
                    $this->report('reset', $instance::class, $throwable);
                }
            }
        }

        foreach ($this->poolEntries as $poolEntry) {
            try {
                $poolEntry->pool->releaseFromCoroutine();
            } catch (Throwable $throwable) {
                $this->report('release', $poolEntry->pool::class, $throwable);
            }
        }
    }

    public function count(): int
    {
        return count($this->poolEntries);
    }

    /**
     * Reported to the error log rather than through the application's logger, which is itself pooled
     * and, by the time the release loop is running, may already have been handed back - asking for it
     * again here would check an instance out of the pool that nothing is left to return.
     *
     * Deliberately loud about which service it was: a failure swallowed here is one nobody would
     * otherwise hear about, since the request it belonged to has already answered.
     */
    private function report(string $stage, string $subject, Throwable $throwable): void
    {
        error_log(sprintf(
            'Service pool %s failed for %s: %s: %s',
            $stage,
            $subject,
            $throwable::class,
            $throwable->getMessage(),
        ));
    }
}
