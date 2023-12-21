<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container\ServicePool;

use K911\Swoole\Bridge\Symfony\Container\Resetter;
use K911\Swoole\Bridge\Symfony\Container\StabilityChecker;
use K911\Swoole\Component\Locking\Mutex;
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
