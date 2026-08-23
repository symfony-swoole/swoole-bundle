<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use Assert\Assertion;
use Closure;
use SwooleBundle\SwooleBundle\Bridge\Doctrine\DoctrineProcessor;
use SwooleBundle\SwooleBundle\Bridge\Monolog\MonologProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\{
    ClassModificationProcessor,
    CompileProcessor,
    Proxifier,
    Tags,
    UnmanagedFactoryProxifier,
};
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Cache\CacheAdapterProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\BlockingContainer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\StabilityChecker;
use SwooleBundle\SwooleBundle\Bridge\Symfony\EventDispatcher\EventDispatcherProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Form\FormProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpClient\HttpClientProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Mailer\MailerProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\MessengerProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Security\SecurityProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\TaskWorkerProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Twig\TwigProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\WebProfiler\WebProfilerProcessor;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Contracts\Service\ResetInterface;
use UnexpectedValueException;

final class StatefulServicesPass implements CompilerPassInterface
{
    private const array IGNORED_SERVICES = [
        BlockingContainer::class => true,
    ];

    private const array MANDATORY_SERVICES_TO_PROXIFY = [
        'kernel_proxy',
        'http_kernel',
        'annotations.reader',
        'logger',
        'profiler_listener',
        'debug.debug_handlers_listener',
        'debug.stopwatch',
        'request_stack',
        'router.request_context',
        'router',
        'router.default',
        'swoole_bundle.error_handler.symfony_error_handler',
    ];

    /**
     * Services which are proxified only when their definition allows it. Applications are free to redefine them
     * in a way which cannot be proxified at all, in such a case the proxification is silently skipped.
     */
    private const array OPTIONAL_SERVICES_TO_PROXIFY = [
        'slugger',
        // The translator carries the current request's locale: LocaleAwareListener writes it on every
        // kernel.request, so one shared instance has every concurrent request overwriting the locale of
        // all the others - a silent wrong-language bug. Both ids are listed for the same reason
        // `router` and `router.default`
        // are: `translator` is an alias, and in debug it resolves to the data collector decorating the
        // real one. Optional rather than mandatory because translation can be turned off entirely and
        // applications routinely decorate or replace the translator.
        'translator',
        'translator.default',
        // Same defect, same listener, one service along: LocaleSwitcher is tagged kernel.locale_aware
        // as well and keeps the locale in a property of its own, so leaving it shared would just move
        // the exception rather than fix it.
        'translation.locale_switcher',
        // Holds the firewall the current request matched, in $currentFirewallName and
        // $currentFirewallContext. SecurityBundle's FirewallListener writes them on kernel.request and
        // nulls them again on kernel.finish_request, so concurrent requests through different firewalls
        // hand each other the wrong logout URL - and each other's null, once one of them finishes.
        'security.logout_url_generator',
        // The security token is per-request state in both layers. `security.untracked_token_storage` is
        // the plain TokenStorage holding $token and the $initializer that LazyFirewallContext installs
        // on every authenticate(), and `security.token_storage` is the UsageTrackingTokenStorage wrapping
        // it, whose $enableUsageTracking the context listener toggles per request through a callback
        // wired in by RegisterTokenUsageTrackingPass. Sharing either across coroutines means sharing who
        // is logged in, so both are pooled - they already carry the kernel.reset tags the pool needs to
        // hand a clean instance to the next request.
        'security.untracked_token_storage',
        'security.token_storage',
        // The same defect SecurityProcessor fixes in AccessDecisionManager, one layer up in the class
        // that calls into it - and the one that actually shows, because templates ask it directly by
        // way of `is_granted()`. isGranted() pushes the decision being made onto $accessDecisionStack
        // and pops it in a finally, isGrantedForUser() does the same with $tokenStack, so a voter doing
        // any I/O suspends its coroutine mid-decision and leaves the stack non-empty for whoever runs
        // next: end() hands that request somebody else's decision, and the pops unwind in an order
        // nobody intended. Both stacks balance themselves, so no resetter is needed - one instance per
        // coroutine is the whole fix.
        'security.authorization_checker',
        // Holds when the consumer it belongs to started, in $workerStartedAt, and compares it against
        // the timestamp `messenger:stop-workers` writes to decide whether to stop. That is per-consumer
        // state on a service the container shares, which costs nothing while a process runs one
        // consumer - and is wrong the moment it runs two, as a task worker command group does: the
        // second WorkerStartedEvent overwrites the first consumer's start time, so a restart request
        // between the two starts is answered by neither.
        //
        // No resetter is needed: every run stamps it on WorkerStartedEvent before anything reads it.
        'messenger.listener.stop_worker_on_restart_signal_listener',
        // The same shape one listener along: $collect is raised when a message is received and lowered
        // when the worker next goes idle, so that the gc runs once per message rather than once per
        // poll. Two consumers in one process share the latch, and the one that goes idle first clears
        // it for the one still working, so the collection runs against a message that is not finished
        // and skips one that is.
        //
        // Pooling gives each consumer its own latch. What it cannot give them is their own peak memory
        // reading - memory_reset_peak_usage() is process-wide - so the per-message peak stays a figure
        // for the whole process whenever a group runs more than one consumer.
        'messenger.listener.reset_memory_usage',
        // The middleware that holds back messages dispatched from inside a handler until the handler
        // that dispatched them has returned. It does that with two properties of its own -
        // $isRootDispatchCallRunning, saying whether a dispatch is already in progress, and $queue,
        // holding what has been held back - on one instance shared by every bus in the process.
        //
        // One dispatch at a time is the assumption, and it holds for a console command or a single
        // consumer. It does not hold for two consumers in one task worker, nor in fact for two http
        // requests dispatching at once, which is the same defect this bundle exists to fix elsewhere.
        //
        // What it costs is quiet and hard to trace back: the second dispatch to arrive sees a root
        // call already running and queues its message behind the first one's, so a message meant to
        // go out when handler A returned goes out when handler B did - or, if the flush has already
        // run, not at all.
        //
        // Both properties are cleared in a finally at the end of a root dispatch, so a coroutine's
        // instance comes back to the pool empty and no resetter is needed.
        'messenger.middleware.dispatch_after_current_bus',
    ];

    private const array SERVICE_RESETTING_PRIORITIES = [
        'profiler' => 1000,
    ];

    private const array COMPILE_PROCESSORS = [
        EventDispatcherProcessor::class => [
            'class' => EventDispatcherProcessor::class,
            'priority' => 0,
        ],
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
        TwigProcessor::class => [
            'class' => TwigProcessor::class,
            'priority' => 0,
        ],
        FormProcessor::class => [
            'class' => FormProcessor::class,
            'priority' => 0,
        ],
        SecurityProcessor::class => [
            'class' => SecurityProcessor::class,
            'priority' => 0,
        ],
        MessengerProcessor::class => [
            'class' => MessengerProcessor::class,
            'priority' => 0,
        ],
        MailerProcessor::class => [
            'class' => MailerProcessor::class,
            'priority' => 0,
        ],
        TaskWorkerProcessor::class => [
            'class' => TaskWorkerProcessor::class,
            'priority' => 0,
        ],
        WebProfilerProcessor::class => [
            'class' => WebProfilerProcessor::class,
            'priority' => 0,
        ],
        HttpClientProcessor::class => [
            'class' => HttpClientProcessor::class,
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

        $this->detectKernelClass($container);
        $modificationProcessor = new ClassModificationProcessor($container);
        $proxifier = $this->createDefaultProxifier($container, $modificationProcessor);
        $this->runCompileProcessors($container, $proxifier);
        $resetters = $this->getServiceResetters($container);
        $this->proxifyKnownStatefulServices($container, $proxifier, $resetters);
        $this->proxifyUnmanagedFactories($container, $modificationProcessor, $resetters);
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
        $dataCollectorServices = $container->findTaggedServiceIds('data_collector');
        $servicesToProxify = array_merge(
            array_keys($resettableStatefulServices),
            array_keys($taggedStatefulServices),
            $configuredStatefulServices,
            array_keys($dataCollectorServices),
            self::MANDATORY_SERVICES_TO_PROXIFY,
            self::OPTIONAL_SERVICES_TO_PROXIFY,
        );
        $servicesToProxify = array_unique($servicesToProxify);
        $optionalServices = array_flip(self::OPTIONAL_SERVICES_TO_PROXIFY);

        foreach ($servicesToProxify as $serviceId) {
            if (isset(self::IGNORED_SERVICES[$serviceId])) {
                continue;
            }

            if (!$container->has($serviceId)) {
                continue;
            }

            if (isset($optionalServices[$serviceId]) && !$this->isOptionalServiceProxifiable($container, $serviceId)) {
                continue;
            }

            $resetter = $resetters[$serviceId] ?? null;

            if ($resetter !== null && str_starts_with($resetter, '?')) {
                $definition = $container->findDefinition($serviceId);
                $definitionClass = $definition->getClass();

                if ($definitionClass !== null && interface_exists($definitionClass)) {
                    $resetter = null;
                } else {
                    Assertion::classExists($definitionClass);
                    $resetter = substr($resetter, 1);

                    if (!method_exists($definitionClass, $resetter)) {
                        $resetter = null;
                    }
                }
            }

            $resetter ??= $this->resolveResetInterfaceResetter($container, $serviceId);

            $resetPriority = self::SERVICE_RESETTING_PRIORITIES[$serviceId] ?? 0;
            $proxifier->proxifyService($serviceId, $resetter, $resetPriority);
        }
    }

    /**
     * The resetters above come from Symfony's `services_resetter`, which only ever lists services
     * tagged `kernel.reset`. That tag is added automatically to every `ResetInterface` implementation
     * which goes through autoconfiguration - but bundles registering their own services (FrameworkBundle's
     * data collectors, MonologBundle's loggers, DoctrineBundle's and SecurityBundle's debug/traceable
     * decorators, ...) do not autoconfigure them, so they carry no `kernel.reset` tag at all.
     *
     * Such a service still gets pooled here (it is a data collector, or it is reachable through one of
     * the other stateful-service sources above), but with a null resetter it is never reset, while its
     * instance keeps being recycled across coroutines - so whatever it accumulates during one request
     * is still there for every later request served by the same instance, forever.
     *
     * Implementing `ResetInterface` is Symfony's own declaration that `reset()` is the correct way to
     * return the service to its between-requests state, so it is safe to fall back to it whenever no
     * explicit resetter was configured.
     */
    private function resolveResetInterfaceResetter(ContainerBuilder $container, string $serviceId): ?string
    {
        $definitionClass = $container->findDefinition($serviceId)->getClass();

        if ($definitionClass === null || !class_exists($definitionClass)) {
            return null;
        }

        if (!is_a($definitionClass, ResetInterface::class, true)) {
            return null;
        }

        return 'reset';
    }

    /**
     * A service cannot be proxified when there is no concrete class to generate the proxy from, which happens
     * when the service definition class is an interface or when it is not known at all.
     */
    private function isOptionalServiceProxifiable(ContainerBuilder $container, string $serviceId): bool
    {
        $definitionClass = $container->findDefinition($serviceId)->getClass();

        return $definitionClass !== null && !interface_exists($definitionClass);
    }

    /**
     * @param array<string, string> $resetters
     */
    private function proxifyUnmanagedFactories(
        ContainerBuilder $container,
        ClassModificationProcessor $modificationProcessor,
        array $resetters,
    ): void {
        $factoryProxifier = new UnmanagedFactoryProxifier($container, $modificationProcessor);
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
        ClassModificationProcessor $modificationProcessor,
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

        return new Proxifier($container, $modificationProcessor, $stabilityCheckers);
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
        $poolEntryDefs = $proxifier->getProxifiedServicePoolEntryDefs();
        $poolContainerDef = $container->findDefinition(ServicePoolContainer::class);
        $poolContainerDef->setArgument(0, $poolEntryDefs);
    }

    private function detectKernelClass(ContainerBuilder $container): void
    {
        $kernelClass = $this->resolveKernelClass($container);
        if ($kernelClass === null) {
            throw new UnexpectedValueException('Cannot detect kernel class.');
        }

        $kernelProxy = $container->findDefinition('kernel_proxy');
        $kernelProxy->setClass($kernelClass);
    }

    private function resolveKernelClass(ContainerBuilder $container): ?string
    {
        if ($container->hasDefinition('kernel')) {
            $kernelClass = $container->getDefinition('kernel')->getClass();

            if ($kernelClass !== null && $kernelClass !== '') {
                return $kernelClass;
            }
        }

        foreach ($container->getResources() as $resource) {
            if (!$resource instanceof FileResource) {
                continue;
            }

            $content = file_get_contents($resource->getResource());
            Assertion::string($content);

            if (!preg_match('/namespace\s+([A-Za-z0-9_\\\\]+);/', $content, $namespaceMatches)) {
                continue;
            }

            if (preg_match('/class\s+([A-Za-z0-9_]+)\s+extends\s+[A-Za-z0-9_]*Kernel\b/', $content, $classMatches)) {
                return $namespaceMatches[1] . '\\' . $classMatches[1];
            }
        }

        return null;
    }
}
