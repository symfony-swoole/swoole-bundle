<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container\ServicePool;

interface ServicePool
{
    public function get(): object;

    public function releaseFromCoroutine(int $cId): void;
}
