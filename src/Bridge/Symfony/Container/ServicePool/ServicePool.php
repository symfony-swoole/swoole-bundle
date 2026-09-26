<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool;

/**
 * @template T of object
 */
interface ServicePool
{
    /**
     * @return T
     */
    public function get(): object;

    public function getAssigned(): ?object;

    public function releaseFromCoroutine(): void;

    /**
     * Drops the instance assigned to the running coroutine if the stability checker rejects it, so the
     * next get() builds a fresh one.
     *
     * For loops that keep one coroutine across many units of work - messenger:consume, whether run in a
     * task worker or as a plain console command - where releaseFromCoroutine() never runs between units
     * and an instance that has gone bad (a closed EntityManager, above all) would otherwise be handed
     * out for the rest of the process' life.
     */
    public function discardUnstableAssigned(): void;

    /**
     * Lets go of every instance no coroutine is holding, for the end of a worker: nothing will ask for one again.
     *
     * An instance is destroyed where the last reference to it goes, and its destructor may do I/O - a mail
     * transport says QUIT and waits for the answer. With the runtime hooks on, a socket opened inside a coroutine
     * can only wait inside one, so an instance left in the pool until the process ends is destroyed after the
     * scheduler has gone, and that wait is a fatal "API must be called in the coroutine". Called from inside a
     * coroutine, before the scheduler ends, this is where the pool's instances are destroyed instead.
     *
     * Instances assigned to a coroutine are left alone: that coroutine is still using them, and gives them back
     * when it ends.
     */
    public function drain(): void;
}
