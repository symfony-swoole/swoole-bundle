<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use Override;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Router\LockingConfigCacheFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Router\LockingLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class RouterOptimizerPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
            return;
        }

        if (!$container->getParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
            return;
        }

        $loaderDef = $container->findDefinition(LockingLoader::class);
        $loaderDef->setDecoratedService('routing.loader');
        $loaderDef->setArgument('$decorated', new Reference('.inner'));

        $cacheFactoryDef = $container->findDefinition(LockingConfigCacheFactory::class);
        $cacheFactoryDef->setDecoratedService('config_cache_factory');
        $cacheFactoryDef->setArgument('$decorated', new Reference('.inner'));
    }
}
