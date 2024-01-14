<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use Symfony\Component\DependencyInjection\ContainerBuilder;

interface CompileProcessor
{
    public function process(ContainerBuilder $container, Proxifier $proxifier): void;
}
