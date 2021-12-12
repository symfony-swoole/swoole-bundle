<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Kernel;

use K911\Swoole\Bridge\Symfony\Container\BlockingContainer;
use K911\Swoole\Reflection\FinalClassModifier;

trait CoroutinesSupportingKernel
{
    /**
     * for the coroutines to work properly, the kernel __clone method has to be overriden,
     * otherwise the container wouldn't be shared between requests.
     */
    public function __clone()
    {
    }

    protected function getContainerBaseClass(): string
    {
        return BlockingContainer::class;
    }

    protected function initializeContainer()
    {
        FinalClassModifier::initialize($this->getCacheDir());

        parent::initializeContainer();
    }
}
