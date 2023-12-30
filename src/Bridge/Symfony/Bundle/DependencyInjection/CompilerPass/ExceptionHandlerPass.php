<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use K911\Swoole\Bridge\Symfony\ErrorHandler\SymfonyExceptionHandler;
use K911\Swoole\Server\RequestHandler\ExceptionHandler\ExceptionHandlerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Deactivates default framework.handle_all_throwables (set to true as default by Symfony 7)
 * if SymfonyExceptionHandler is not activated.
 */
final class ExceptionHandlerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (Kernel::MAJOR_VERSION === 5) {
            return;
        }

        /** @phpstan-ignore-next-line */
        $handlerAlias = $container->getAlias(ExceptionHandlerInterface::class);

        if (SymfonyExceptionHandler::class !== (string) $handlerAlias) {
            $kernelDef = $container->findDefinition('http_kernel');
            $kernelDef->setArgument('$handleAllThrowables', false);
        }
    }
}
