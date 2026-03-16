<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use RuntimeException;
use SwooleBundle\SwooleBundle\Reflection\ClassModifier;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ClassModificationProcessor
{
    private string $cacheDir;

    /**
     * @var array<string, true>
     */
    private array $processedClasses = [];

    public function __construct(ContainerBuilder $container)
    {
        $this->setCacheDir($container);
    }

    /**
     * @param class-string $className
     */
    public function processFinalClass(string $className): void
    {
        if (isset($this->processedClasses[$className])) {
            return;
        }

        $this->processedClasses[$className] = true;
        ClassModifier::removeFinalFlagsFromClass($className);
        ClassModifier::dumpCache($this->cacheDir);
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
