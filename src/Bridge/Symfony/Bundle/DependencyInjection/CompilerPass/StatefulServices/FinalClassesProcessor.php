<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use K911\Swoole\Reflection\FinalClassModifier;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class FinalClassesProcessor
{
    private ContainerBuilder $container;

    private array $processedClasses = [];

    public function __construct(ContainerBuilder $container)
    {
        $this->container = $container;
    }

    public function process(string $className): void
    {
        if (isset($this->processedClasses[$className])) {
            return;
        }

        $this->processedClasses[$className] = true;
        FinalClassModifier::removeFinalFlagsFromClass($className);
        FinalClassModifier::dumpCache((string) $this->container->getParameter('kernel.cache_dir'));
    }
}
