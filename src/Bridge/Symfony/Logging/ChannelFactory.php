<?php
declare(strict_types=1);
/*
 * @author     mfris
 * @copyright  PIXELFEDERATION s.r.o.
 * @license    Internal use only
 */

namespace K911\Swoole\Bridge\Symfony\Logging;

use K911\Swoole\Server\Config\WorkerEstimatorInterface;
use Swoole\Coroutine\Channel;

/**
 *
 */
final class ChannelFactory
{
    /**
     * @var WorkerEstimatorInterface
     */
    private $workerEstimator;

    /**
     * @var int|null
     */
    private $workerCount;

    /**
     * @param WorkerEstimatorInterface $workerEstimator
     * @param int|null                 $workerCount
     */
    public function __construct(WorkerEstimatorInterface $workerEstimator, ?int $workerCount)
    {
        $this->workerEstimator = $workerEstimator;
        $this->workerCount = $workerCount;
    }

    /**
     * @return Channel
     */
    public function newInstance(): Channel
    {
        return new Channel($this->getWorkerCount() * 1024 * 100);
    }

    /**
     * @return int
     */
    private function getWorkerCount(): int
    {
        if (!$this->workerCount) {
            $this->workerCount = $this->workerEstimator->getDefaultWorkerCount();
        }

        return $this->workerCount;
    }
}
