<?php
declare(strict_types=1);

/*
 * @author Martin Fris <rasta@lj.sk>
 */

namespace K911\Swoole\Server\Config;

/**
 *
 */
interface WorkerEstimatorInterface
{
    /**
     * @return int
     */
    public function getDefaultReactorCount(): int;

    /**
     * @return int
     */
    public function getDefaultWorkerCount(): int;
}
