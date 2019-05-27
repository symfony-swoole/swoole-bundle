<?php
declare(strict_types=1);

/*
 * @author Martin Fris <rasta@lj.sk>
 */

namespace K911\Swoole\Tests\Unit\Bridge\Symfony\Logging;

use K911\Swoole\Bridge\Symfony\Logging\ChannelFactory;
use K911\Swoole\Server\Config\WorkerEstimatorInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\MethodProphecy;
use Prophecy\Prophecy\ObjectProphecy;

/**
 *
 */
class ChannelFactoryTest extends TestCase
{
    /**
     *
     */
    public function testNewInstanceWithWorkerCountNotSetByDefault(): void
    {
        /* @var $estimator ObjectProphecy|WorkerEstimatorInterface */
        $estimator = $this->prophesize(WorkerEstimatorInterface::class);
        /* @var $getCountMethod MethodProphecy */
        $getCountMethod = $estimator->getDefaultWorkerCount();
        $getCountMethod->shouldBeCalled()->willReturn(1);
        /* @var $estimatorInstance WorkerEstimatorInterface */
        $estimatorInstance = $estimator->reveal();

        $factory = new ChannelFactory($estimatorInstance, null);
        $factory->newInstance();
    }

    /**
     *
     */
    public function testNewInstanceWithWorkerCountSetByDefault(): void
    {
        /* @var $estimator ObjectProphecy|WorkerEstimatorInterface */
        $estimator = $this->prophesize(WorkerEstimatorInterface::class);
        /* @var $getCountMethod MethodProphecy */
        $getCountMethod = $estimator->getDefaultWorkerCount();
        $getCountMethod->shouldNotBeCalled();
        /* @var $estimatorInstance WorkerEstimatorInterface */
        $estimatorInstance = $estimator->reveal();

        $factory = new ChannelFactory($estimatorInstance, 1);
        $factory->newInstance();
    }
}
