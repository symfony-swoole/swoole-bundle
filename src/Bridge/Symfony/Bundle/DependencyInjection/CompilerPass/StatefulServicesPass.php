<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use Assert\Assertion;
use Closure;
use SwooleBundle\SwooleBundle\Bridge\Doctrine\DoctrineProcessor;
use SwooleBundle\SwooleBundle\Bridge\Monolog\MonologProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\{
    CompileProcessor,
    FinalClassesProcessor,
    Proxifier,
    Tags,
    UnmanagedFactoryProxifier,
};
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Cache\CacheAdapterProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\BlockingContainer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\StabilityChecker;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use UnexpectedValueException;

final class StatefulServicesPass implements CompilerPassInterface
{
    private const IGNORED_SERVICES = [
        BlockingContainer::class => true,
    ];

    private const MANDATORRY_SERVICES_TO_PROXIFY = [
        'annotations.reader',
        'logger',
        'profiler_listener',
        'debug.event_dispatcher.inner',
        'debug.stopwatch',
        'request_stack',
    ];

    private const COMPILE_PROCESSORS = [
        CacheAdapterProcessor::class => [
            'class' => CacheAdapterProcessor::class,
            'priority' => 0,
        ],
        DoctrineProcessor::class => [
            'class' => DoctrineProcessor::class,
            'priority' => 0,
        ],
        MonologProcessor::class => [
            'class' => MonologProcessor::class,
            'priority' => 0,
        ],
    ];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
            return;
        }

        if (!$container->getParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
            return;
        }

        $finalProcessor = new FinalClassesProcessor($container);
        $proxifier = $this->createDefaultProxifier($container, $finalProcessor);
        $this->runCompileProcessors($container, $proxifier);
        $resetters = $this->getServiceResetters($container);
        $this->proxifyKnownStatefulServices($container, $proxifier, $resetters);
        $this->proxifyUnmanagedFactories($container, $finalProcessor, $resetters);
        $this->reduceServiceResetters($container);
        $this->configureServicePoolContainer($container, $proxifier);
    }

    private function runCompileProcessors(ContainerBuilder $container, Proxifier $proxifier): void
    {
        $compileProcessors = $container->getParameter(ContainerConstants::PARAM_COROUTINES_COMPILE_PROCESSORS);

        if (!is_array($compileProcessors)) {
            throw new UnexpectedValueException('Invalid compiler processors provided');
        }

        /** @var array<string, mixed>|null $doctrineConfig */
        $doctrineConfig = $container->hasParameter(
            ContainerConstants::PARAM_COROUTINES_DOCTRINE_COMPILE_PROCESSOR_CONFIG
        )
            ? $container->getParameter(ContainerConstants::PARAM_COROUTINES_DOCTRINE_COMPILE_PROCESSOR_CONFIG)
            : null;

        $defaultProcessors = self::COMPILE_PROCESSORS;

        if ($doctrineConfig !== null) {
            $defaultProcessors[DoctrineProcessor::class]['config'] = $doctrineConfig;
        }

        /** @var array<array{class: class-string<CompileProcessor>, priority: int}> $compileProcessors */
        $compileProcessors = array_merge(array_values($defaultProcessors), $compileProcessors);

        /**
         * @var Closure(
         *  array<int, array<array{class: class-string<CompileProcessor>, config?: array<string, mixed>}>>,
         *  array{class: class-string<CompileProcessor>, priority?: int, config?: array<string, mixed>}
         *  ): array<int, array<array{class: class-string<CompileProcessor>, config?: array<string, mixed>}>> $reducer
         * @phpstan-ignore varTag.nativeType
         */
        $reducer = static function (array $processors, array $processorConfig): array {
            $priority = $processorConfig['priority'] ?? 0;
            $processors[$priority][] = $processorConfig;

            return $processors;
        };

        $compileProcessors = array_reduce(
            $compileProcessors,
            $reducer,
            []
        );
        /**
         * @var array<int, array{
         *     class: class-string<CompileProcessor>,
         *     priority?: int,
         *     config?: array<string, mixed>
         * }> $compileProcessors
         */
        $compileProcessors = array_merge(...array_reverse($compileProcessors));

        foreach ($compileProcessors as $processorConfig) {
            /** @var CompileProcessor $processor */
            $processor = isset($processorConfig['config'])
                ? new $processorConfig['class']($processorConfig['config'])
                : new $processorConfig['class']();
            $processor->process($container, $proxifier);
        }
    }

    /**
     * @param array<string, string> $resetters
     */
    private function proxifyKnownStatefulServices(
        ContainerBuilder $container,
        Proxifier $proxifier,
        array $resetters,
    ): void {
        /** @var array<string, array<string, mixed>|null> $resettableStatefulServices */
        $resettableStatefulServices = $container->findTaggedServiceIds('kernel.reset');
        /** @var array<string, array<string, mixed>|null> $taggedStatefulServices */
        $taggedStatefulServices = $container->findTaggedServiceIds(ContainerConstants::TAG_STATEFUL_SERVICE);
        /** @var array<string> $configuredStatefulServices */
        $configuredStatefulServices = $container->getParameter(ContainerConstants::PARAM_COROUTINES_STATEFUL_SERVICES);
        $servicesToProxify = array_merge(
            array_keys($resettableStatefulServices),
            array_keys($taggedStatefulServices),
            $configuredStatefulServices,
            self::MANDATORRY_SERVICES_TO_PROXIFY
        );
        $servicesToProxify = array_unique($servicesToProxify);

        foreach ($servicesToProxify as $serviceId) {
            if (isset(self::IGNORED_SERVICES[$serviceId])) {
                continue;
            }

            if (!$container->has($serviceId)) {
                continue;
            }

            $resetter = $resetters[$serviceId] ?? null;

            if ($resetter !== null && str_starts_with($resetter, '?')) {
                $definition = $container->findDefinition($serviceId);
                $definitionClass = $definition->getClass();
                Assertion::classExists($definitionClass);
                $resetter = substr($resetter, 1);

                if (!method_exists($definitionClass, $resetter)) {
                    $resetter = null;
                }
            }

            $proxifier->proxifyService($serviceId, $resetter);
        }
    }

    /**
     * @param array<string, string> $resetters
     */
    private function proxifyUnmanagedFactories(
        ContainerBuilder $container,
        FinalClassesProcessor $finalProcessor,
        array $resetters,
    ): void {
        $factoryProxifier = new UnmanagedFactoryProxifier($container, $finalProcessor);
        /** @var array<string, array<string, mixed>|null> $factoriesToProxify */
        $factoriesToProxify = $container->findTaggedServiceIds(ContainerConstants::TAG_UNMANAGED_FACTORY);
        $factoriesToProxify = array_unique(array_keys($factoriesToProxify));

        foreach ($factoriesToProxify as $serviceId) {
            if (isset(self::IGNORED_SERVICES[$serviceId])) {
                continue;
            }

            if (!$container->has($serviceId)) {
                continue;
            }

            $factoryProxifier->proxifyService($serviceId);
        }
    }

    private function createDefaultProxifier(
        ContainerBuilder $container,
        FinalClassesProcessor $finalProcessor,
    ): Proxifier {
        $stabilityCheckerDefs = $container->findTaggedServiceIds(ContainerConstants::TAG_STABILITY_CHECKER);
        /** @var array<class-string, class-string<StabilityChecker>|string> $stabilityCheckers */
        $stabilityCheckers = [];

        foreach (array_keys($stabilityCheckerDefs) as $svcId) {
            $definition = $container->findDefinition($svcId);
            /** @var class-string<StabilityChecker> $svcClass */
            $svcClass = $definition->getClass();
            /** @var class-string $supportedClass */
            $supportedClass = call_user_func([$svcClass, 'getSupportedClass']);
            $stabilityCheckers[$supportedClass] = $svcId;
        }

        return new Proxifier($container, $finalProcessor, $stabilityCheckers);
    }

    /**
     * @return array<string, string>
     */
    private function getServiceResetters(ContainerBuilder $container): array
    {
        $resetterDef = $container->findDefinition('services_resetter');
        /** @var array<string, list<string>> $resetters */
        $resetters = $resetterDef->getArgument(1);

        return array_map(static fn(array $r): string => $r[0], $resetters);
    }

    private function reduceServiceResetters(ContainerBuilder $container): void
    {
        $resetterDef = $container->findDefinition('services_resetter');
        /** @var ServiceLocatorArgument $resetters */
        $resetters = $resetterDef->getArgument(0);
        $resetMethods = $resetterDef->getArgument(1);
        Assertion::isArray($resetMethods);
        $newResetters = [];
        $newResetMethods = [];

        foreach ($resetters->getValues() as $serviceId => $value) {
            $valueDef = $container->findDefinition((string) $value);
            /** @var class-string $classString */
            $classString = $valueDef->getClass();
            $tags = new Tags($classString, $valueDef->getTags());

            if (!$tags->resetOnEachRequest()) {
                continue;
            }

            $newResetters[$serviceId] = $value;
            $newResetMethods[$serviceId] = $resetMethods[$serviceId];
        }

        $resetters->setValues($newResetters);
        $resetterDef->setArgument(1, $newResetMethods);
    }

    private function configureServicePoolContainer(ContainerBuilder $container, Proxifier $proxifier): void
    {
        $poolRefs = $proxifier->getProxifiedServicePoolRefs();
        $poolContainerDef = $container->findDefinition(ServicePoolContainer::class);
        $poolContainerDef->setArgument(0, $poolRefs);
    }
}
