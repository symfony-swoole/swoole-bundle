<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use Override;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

final class CacheWarmupFixerPass implements CompilerPassInterface
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

        if (Kernel::MAJOR_VERSION > 7) { // @phpstan-ignore-line
            return;
        }

        $kernelDef = new Definition('kernel_original');
        $kernelDef->setClass(KernelInterface::class);
        $kernelDef->setPublic(true);
        $kernelDef->setSynthetic(true);
        $container->setDefinition('kernel_original', $kernelDef);

        $warmerDef = $container->findDefinition('config_builder.warmer');
        $warmerDef->setArgument('$kernel', new Reference('kernel_original'));
    }
}
