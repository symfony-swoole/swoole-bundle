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
}
