<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel\Controller\NonMutatingControllerResolver;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerResolver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Swaps FrameworkBundle's ControllerResolver for one that leaves shared controllers alone.
 *
 * Only the class is replaced: the arguments, the allowControllers() call and the tags stay exactly as
 * FrameworkBundle configured them, and NonMutatingControllerResolver keeps the constructor signature of
 * the resolver it replaces.
 *
 * @see NonMutatingControllerResolver for what the writes were and why they are safe to drop
 */
final class ControllerResolverPass implements CompilerPassInterface
{
    private const string SERVICE_ID = 'controller_resolver';

    public function process(ContainerBuilder $container): void
    {
        if (!$this->areCoroutinesEnabled($container) || !$container->hasDefinition(self::SERVICE_ID)) {
            return;
        }

        $definition = $container->getDefinition(self::SERVICE_ID);

        // another bundle may already have put its own resolver in place - leave that one alone rather
        // than silently dropping whatever it does
        if ($definition->getClass() !== ControllerResolver::class) {
            return;
        }

        $definition->setClass(NonMutatingControllerResolver::class);
    }

    private function areCoroutinesEnabled(ContainerBuilder $container): bool
    {
        return $container->hasParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)
            && $container->getParameter(ContainerConstants::PARAM_COROUTINES_ENABLED) === true;
    }
}
