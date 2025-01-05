<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool;

use RuntimeException;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Resetter;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\StabilityChecker;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;
use Symfony\Component\DependencyInjection\Container;

/**
 * @template T of object
 * @template-extends BaseServicePool<T>
 */
final class DiServicePool extends BaseServicePool
{
    public function __construct(
        private readonly string $wrappedServiceId,
        private readonly Container $container,
        Swoole $swoole,
        Mutex $mutex,
        int $instancesLimit = 50,
        ?Resetter $resetter = null,
        ?StabilityChecker $stabilityChecker = null,
    ) {
        parent::__construct($swoole, $mutex, $instancesLimit, $resetter, $stabilityChecker);
    }

    /**
     * @return T
     */
    protected function newServiceInstance(): object
    {
        /** @var T|null $instance */
        $instance = $this->container->get($this->wrappedServiceId);

        if ($instance === null) {
            throw new RuntimeException(sprintf('Service "%s" is not defined.', $this->wrappedServiceId));
        }

        return $instance;
    }
}
