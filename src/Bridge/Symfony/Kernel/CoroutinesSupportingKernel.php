<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Kernel;

use SwooleBundle\SwooleBundle\Bridge\CommonSwoole\SystemSwooleFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\BlockingContainer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Modifier\Modifier;
use SwooleBundle\SwooleBundle\Reflection\FinalClassModifier;

/**
 * @phpstan-ignore trait.unused
 */
trait CoroutinesSupportingKernel
{
    /**
     * for the coroutines to work properly, the kernel __clone method has to be overriden,
     * otherwise the container wouldn't be shared between requests.
     */
    public function __clone()
    {
        // cloned kernel should have a fresh container and other state
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
    protected function initializeContainer(): void
    {
        FinalClassModifier::initialize($this->getCacheDir());
        $cacheDir = $this->getCacheDir();
        $swooleFactory = SystemSwooleFactory::newFactoryInstance();
        BlockingContainer::initializeMutex($swooleFactory->newInstance());

        parent::initializeContainer();

        if (!$this->areCoroutinesEnabled()) {
            return;
        }

        Modifier::modifyContainer($this->container, $cacheDir, $this->isDebug());
    }

    private function areCoroutinesEnabled(): bool
    {
        if (!$this->container->hasParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
            return false;
        }

        return (bool) $this->container->getParameter(ContainerConstants::PARAM_COROUTINES_ENABLED);
    }
}
