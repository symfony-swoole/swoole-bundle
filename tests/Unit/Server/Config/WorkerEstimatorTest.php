<?php
/*
 * @author     mfris
 * @copyright  PIXELFEDERATION s.r.o.
 * @license    Internal use only
 */

namespace K911\Swoole\Tests\Unit\Server\Config;

use K911\Swoole\Server\Config\WorkerEstimator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

/**
 *
 */
class WorkerEstimatorTest extends TestCase
{
    /**
     * @return void
     * @throws ReflectionException
     */
    public function test(): void
    {
        self::mockSwooleCpuNumFuncForClass(WorkerEstimator::class);

        $estimator = new WorkerEstimator();

        self::assertEquals(1, $estimator->getDefaultReactorCount());
        self::assertEquals(2, $estimator->getDefaultWorkerCount());
    }

    /**
     * @param $class
     *
     * @return void
     * @throws ReflectionException
     */
    private static function mockSwooleCpuNumFuncForClass($class): void
    {
        $self = self::class;

        $reflClass = new ReflectionClass($class);
        $ns = $reflClass->getNamespaceName();

        eval(<<<EOPHP
namespace $ns;

function swoole_cpu_num()
{
    return \\$self::swooleCpuNum();
}
EOPHP
        );
    }

    /**
     * @return int
     */
    public static function swooleCpuNum(): int
    {
        return 1;
    }
}
