<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use RuntimeException;
use SwooleBundle\SwooleBundle\Reflection\FinalClassModifier;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class FinalClassesProcessor
{
    private string $cacheDir;

    private array $processedClasses = [];

    public function __construct(ContainerBuilder $container)
    {
        $this->setCacheDir($container);
    }

    public function process(string $className): void
    {
        if (isset($this->processedClasses[$className])) {
            return;
        }

        $this->processedClasses[$className] = true;
        FinalClassModifier::removeFinalFlagsFromClass($className);
        FinalClassModifier::dumpCache($this->cacheDir);
    }

    private function setCacheDir(ContainerBuilder $container): void
    {
        $cacheDir = $container->getParameter('kernel.cache_dir');

        if (!is_string($cacheDir)) {
            throw new RuntimeException('Kernel cache directory is not a string.');
        }

        $this->cacheDir = $cacheDir;
    }
}
