<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool;

use Swoole\Coroutine\Channel;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Disposer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Initializer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\StabilityChecker;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;

/**
 * Every coroutine that asks for the service gets an instance of its own, up to $instancesLimit at a time, and gives
 * it back when it ends; an instance given back waits in the free pool for the next coroutine.
 *
 * The free pool keeps as many instances as were ever in use at once, unless $idleLimit says otherwise: a burst of
 * concurrent requests leaves that many instances behind for the life of the worker, and where an instance holds a
 * server's session - a database connection - each one is a session the server keeps open for nothing. With an idle
 * limit, an instance given back to a free pool that already holds that many is dropped instead, and handed to the
 * $disposer, which lets go of what it holds.
 *
 * An instance is given back as its coroutine ends, from a deferred callback, but that coroutine has not ended yet: it
 * goes on giving back its other instances, and any of that may wait on I/O - a connection's ping, on its way back.
 * Meanwhile the instance can be taken by another coroutine - one waiting at the limit is woken by the very release,
 * and both runtimes switch to it there and then. Two coroutines that are both still running then use one instance: the
 * next one writes what the one before it, not yet ended, wrote last - an entity manager's
 * HydrationCompleteHandler::$deferredPostLoadInvocations, for one.
 *
 * So a coroutine takes from the free pool an instance whose coroutine has ended, and when every free one is still
 * with a coroutine on its way out, waits for that coroutine to be gone. The wait is in the coroutine taking it, which
 * already exists: giving back later instead, from a coroutine or a timer started once the other has ended, is what a
 * runtime limiting how many coroutines run at once (OpenSwoole's max_concurrency) queues behind the coroutines waiting
 * for that very instance - and none of them ever runs again.
 *
 * @template T of object
 * @template-implements ServicePool<T>
 */
abstract class BaseServicePool implements ServicePool
{
    /**
     * How often a coroutine waiting for one on its way out checks whether it is gone. It has nothing left to do but
     * give back the rest of its instances, so the first check is usually enough.
     */
    private const float ENDING_COROUTINE_CHECK_SECONDS = 0.001;

    private int $assignedCount = 0;

    /**
     * @var array<int, T>
     */
    private array $freePool = [];

    /**
     * @var array<int, T>
     */
    private array $assignedPool = [];

    /**
     * The coroutine each instance in the free pool was given back by, by the instance's object id.
     *
     * @var array<int, int>
     */
    private array $givenBackBy = [];

    public function __construct(
        private readonly Swoole $swoole,
        private readonly Mutex $mutex,
        private readonly int $instancesLimit = 50,
        private readonly ?StabilityChecker $stabilityChecker = null,
        private readonly ?Initializer $initializer = null,
        private readonly ?int $idleLimit = null,
        private readonly ?Disposer $disposer = null,
    ) {}

    /**
     * @return T
     */
    public function get(): object
    {
        $cId = $this->getCoroutineId();

        if (isset($this->assignedPool[$cId])) {
            return $this->assignedPool[$cId];
        }

        if ($this->assignedCount >= $this->instancesLimit) {
            // this will wait until a different coroutine will release the lock
            $this->lockPool();
        }

        $this->assignedCount++;

        return $this->assignedPool[$cId] = $this->getServiceToAssign();
    }

    public function getAssigned(): ?object
    {
        $cId = $this->getCoroutineId();

        return $this->assignedPool[$cId] ?? null;
    }

    public function releaseFromCoroutine(): void
    {
        $cId = $this->getCoroutineId();

        if (!isset($this->assignedPool[$cId])) {
            return;
        }

        $service = $this->assignedPool[$cId];
        unset($this->assignedPool[$cId]);
        $this->assignedCount--;

        if (!$this->isServiceStable($service) || $this->isFreePoolFull()) {
            $this->unlockPool();
            $this->dispose($service);

            return;
        }

        $this->freePool[] = $service;
        $this->givenBackBy[spl_object_id($service)] = $cId;
        $this->unlockPool();
    }

    public function discardUnstableAssigned(): void
    {
        $cId = $this->getCoroutineId();

        if (!isset($this->assignedPool[$cId])) {
            return;
        }

        if ($this->isServiceStable($this->assignedPool[$cId])) {
            return;
        }

        // deliberately not returned to the free pool - the same reasoning as the unstable branch of
        // releaseFromCoroutine(), an instance that failed the stability check must not be handed out
        // again. Dropping it here is the whole point: the next get() builds a new one.
        $service = $this->assignedPool[$cId];
        unset($this->assignedPool[$cId]);
        $this->assignedCount--;
        $this->unlockPool();
        $this->dispose($service);
    }

    public function drain(): void
    {
        $drained = $this->freePool;
        $this->freePool = [];
        $this->givenBackBy = [];

        foreach ($drained as $service) {
            $this->dispose($service);
        }
    }

    /**
     * @return T
     */
    abstract protected function newServiceInstance(): object;

    /**
     * @return T
     */
    private function getServiceToAssign(): object
    {
        $assigned = $this->freePool === [] ? $this->newServiceInstance() : $this->takeFreeInstance();
        $this->initializer?->initialize($assigned);

        return $assigned;
    }

    /**
     * One whose coroutine has ended if there is one, and otherwise the one given back first, once its coroutine has
     * ended.
     *
     * @return T
     */
    private function takeFreeInstance(): object
    {
        foreach ($this->freePool as $index => $service) {
            if ($this->isStillEnding($this->givenBackBy[spl_object_id($service)] ?? 0)) {
                continue;
            }

            array_splice($this->freePool, $index, 1);
            unset($this->givenBackBy[spl_object_id($service)]);

            return $service;
        }

        $service = array_shift($this->freePool);

        if ($service === null) {
            return $this->newServiceInstance();
        }

        $givenBackBy = $this->givenBackBy[spl_object_id($service)] ?? 0;
        unset($this->givenBackBy[spl_object_id($service)]);

        while ($this->isStillEnding($givenBackBy)) {
            (new Channel(1))->pop(self::ENDING_COROUTINE_CHECK_SECONDS);
        }

        return $service;
    }

    /**
     * Whether the coroutine that gave an instance back is still running - and is not the one asking, which may take
     * back what it gave.
     */
    private function isStillEnding(int $coroutineId): bool
    {
        return $coroutineId > 0
            && $coroutineId !== $this->getCoroutineId()
            && $this->swoole->getCoroutineContext($coroutineId) !== null;
    }

    private function getCoroutineId(): int
    {
        return $this->swoole->getCoroutineId();
    }

    private function isServiceStable(object $service): bool
    {
        return $this->stabilityChecker === null || $this->stabilityChecker->isStable($service);
    }

    private function isFreePoolFull(): bool
    {
        return $this->idleLimit !== null && count($this->freePool) >= $this->idleLimit;
    }

    /**
     * Called once the instance is out of the pool and the pool's lock is given back, so that a disposer that throws
     * leaves the pool as it would be had the instance been disposed of.
     *
     * @param T $service
     */
    private function dispose(object $service): void
    {
        $this->disposer?->dispose($service);
    }

    private function lockPool(): void
    {
        $this->mutex->acquire();
    }

    private function unlockPool(): void
    {
        if (!$this->mutex->isAcquired()) {
            return;
        }

        $this->mutex->release();
    }
}
