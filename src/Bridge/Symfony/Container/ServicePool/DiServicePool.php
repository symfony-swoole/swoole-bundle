<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Resetter;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\StabilityChecker;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;
use Symfony\Component\DependencyInjection\Container;

/**
 * @template T of object
 *
 * @template-extends BaseServicePool<T>
 */
final class DiServicePool extends BaseServicePool
{
    public function __construct(
        private readonly string $wrappedServiceId,
        private readonly Container $container,
        Mutex $mutex,
        int $instancesLimit = 50,
        ?Resetter $resetter = null,
        ?StabilityChecker $stabilityChecker = null
    ) {
        parent::__construct($mutex, $instancesLimit, $resetter, $stabilityChecker);
    }

    /**
     * @return T
     */
    protected function newServiceInstance(): object
    {
        /** @var null|T $instance */
        $instance = $this->container->get($this->wrappedServiceId);

        if (null === $instance) {
            throw new \RuntimeException(\sprintf('Service "%s" is not defined.', $this->wrappedServiceId));
        }

        return $instance;
    }
}
