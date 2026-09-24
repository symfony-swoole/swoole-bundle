<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Reflection\ClassModifier;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Strips `final` from the classes the service pool proxies, while the container is compiled, and records them in
 * that container - which is the only place the list is kept. A process that loads the container rather than
 * compiling it strips the same classes from the recorded list (CoroutinesSupportingKernel), so the list and the
 * container it belongs to are written together and cannot be lost apart.
 */
final class ClassModificationProcessor
{
    /**
     * @var array<class-string, true>
     */
    private array $processedClasses = [];

    /**
     * @var array<class-string, class-string>
     */
    private array $finalClasses = [];

    public function __construct(private readonly ContainerBuilder $container)
    {
        $this->recordFinalClasses();
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

        if (!ClassModifier::removeFinalFlagsFromClass($className)) {
            return;
        }

        $this->finalClasses[$className] = $className;
        $this->recordFinalClasses();
    }

    private function recordFinalClasses(): void
    {
        $this->container->setParameter(
            ContainerConstants::PARAM_COROUTINES_FINAL_CLASSES,
            array_values($this->finalClasses),
        );
    }
}
