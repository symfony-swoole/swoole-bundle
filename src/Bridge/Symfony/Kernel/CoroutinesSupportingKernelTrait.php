<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Kernel;

use K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use K911\Swoole\Bridge\Symfony\Container\BlockingContainer;
use K911\Swoole\Bridge\Symfony\Container\ContainerModifier;
use K911\Swoole\Reflection\FinalClassModifier;

trait CoroutinesSupportingKernelTrait
{
    /**
     * for the coroutines to work properly, the kernel __clone method has to be overriden,
     * otherwise the container wouldn't be shared between requests.
     */
    public function __clone()
    {
    }

    /**
     * this overrides the container class to a container, which is able to block the first instatiation
     * of requested service instance (because class autoloading is IO operation, which switches coroutine context).
     * the blocking ensures that only one service instance will be created concurrently and it will be registered
     * correctly in the container.
     */
    protected function getContainerBaseClass(): string
    {
        return BlockingContainer::class;
    }

    /**
     * this initializes logic which removes the final flag from proxified classes (if they are final).
     */
    protected function initializeContainer()
    {
        FinalClassModifier::initialize($this->getCacheDir());

        parent::initializeContainer();

        if (!$this->container->hasParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
            return;
        }

        if (!$this->container->getParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
            return;
        }

        ContainerModifier::modifyContainer($this->container);
    }
}
