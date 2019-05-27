<?php
declare(strict_types=1);

/*
 * @author Martin Fris <rasta@lj.sk>
 */

namespace K911\Swoole\Server\Config;

/**
 *
 */
final class WorkerEstimator implements WorkerEstimatorInterface
{
    /**
     * @var int
     */
    private $numCpus;

    /**
     * @return int
     */
    public function getDefaultReactorCount(): int
    {
        return $this->getNumCpus();
    }

    /**
     * @return int
     */
    public function getDefaultWorkerCount(): int
    {
        return $this->getNumCpus() * 2;
    }

    /**
     * @return int
     */
    private function getNumCpus()
    {
        if (!$this->numCpus) {
            $this->numCpus = swoole_cpu_num();
        }

        return $this->numCpus;
    }
}
