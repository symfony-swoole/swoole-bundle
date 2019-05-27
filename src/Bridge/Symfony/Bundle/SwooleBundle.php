<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle;

use K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\LoggerReplacerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SwooleBundle extends Bundle
{
    /**
     * @param ContainerBuilder $container
     *
     * @return void
     */
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new LoggerReplacerPass());
    }
}
