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

    public function releaseFromCoroutine(int $cId): void;
}
