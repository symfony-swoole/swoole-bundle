<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\DependencyInjection\CompilerPass;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\Proxifier;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller\DoctrineController;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Initializer\CountingInitializer;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Resetter\CountingResetter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class CounterCompileProcessor implements CompileProcessor
{
    public function process(ContainerBuilder $container, Proxifier $proxifier): void
    {
        $this->decorateInitializer(
            $container,
            'swoole_bundle.coroutines_support.doctrine.connection_initializer.default',
        );
        $this->decorateResetter($container, 'inmemory_repository_resetter');
    }

    private function decorateInitializer(ContainerBuilder $container, string $initializerId): void
    {
        $formerResetterDef = $container->findDefinition($initializerId);
        $newId = $initializerId . '.inner';
        $container->setDefinition($newId, $formerResetterDef);
        $counterDef = new Definition();
        $counterDef->setClass(CountingInitializer::class);
        $counterDef->setArgument(0, new Reference($newId));
        $container->setDefinition($initializerId, $counterDef);

        $controllerDef = $container->findDefinition(DoctrineController::class);
        /** @var array<string, Reference> $initializers */
        $initializers = $controllerDef->getArgument(3);
        $initializers[$initializerId] = new Reference($initializerId);
        $controllerDef->setArgument(3, $initializers);
    }

    private function decorateResetter(ContainerBuilder $container, string $resetterId): void
    {
        $formerResetterDef = $container->findDefinition($resetterId);
        $newId = $resetterId . '.inner';
        $container->setDefinition($newId, $formerResetterDef);
        $counterDef = new Definition();
        $counterDef->setClass(CountingResetter::class);
        $counterDef->setArgument(0, new Reference($newId));
        $container->setDefinition($resetterId, $counterDef);

        $controllerDef = $container->findDefinition(DoctrineController::class);
        /** @var array<string, Reference> $resetters */
        $resetters = $controllerDef->getArgument(4);
        $resetters[$resetterId] = new Reference($resetterId);
        $controllerDef->setArgument(4, $resetters);
    }
}
