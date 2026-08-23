<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection;

use Composer\InstalledVersions;
use Exception;
use Monolog\Formatter\LineFormatter;
use Override;
use ReflectionMethod;
use RuntimeException;
use SwooleBundle\SwooleBundle\Bridge\CommonSwoole\SystemSwooleFactory;
use SwooleBundle\SwooleBundle\Bridge\Log\AccessLogFormatter;
use SwooleBundle\SwooleBundle\Bridge\Log\SimpleAccessLogFormatter;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\EventDispatcher\DebugClassLoaderOverridingWorkerStartHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\StabilityChecker;
use SwooleBundle\SwooleBundle\Bridge\Symfony\ErrorHandler\ErrorHandlerResetter;
use SwooleBundle\SwooleBundle\Bridge\Symfony\ErrorHandler\ErrorResponder;
use SwooleBundle\SwooleBundle\Bridge\Symfony\ErrorHandler\ExceptionHandlerFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\ErrorHandler\SymfonyExceptionHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\ErrorHandler\ThrowableHandlerFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\AccessLogOnKernelTerminate;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\CloudFrontRequestFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\RequestFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\ResponseProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\TrustAllProxiesRequestHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel\ContextReleasingHttpKernelRequestHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel\HttpKernelRequestHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\ExceptionLoggingTransportHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\ResetServicePoolsBetweenMessages;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\ServiceResettingTransportHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\ApplicationCommandResolver;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\CommandGroupRunner;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\Exception\CommandNotRunnable;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\LongRunningCommandsWorkerStartHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\RaiseStopSignalOnWorkerShutdown;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\StopMessengerWorkerOnShutdown;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\StreamCommandOutputFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\TaskWorkerCommands;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\WithWorkerStopSignal;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\WorkerRetirement;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\WorkerStopSignal;
use SwooleBundle\SwooleBundle\Bridge\Tideways\Apm\Apm;
use SwooleBundle\SwooleBundle\Bridge\Tideways\Apm\RequestDataProvider;
use SwooleBundle\SwooleBundle\Bridge\Tideways\Apm\RequestProfiler;
use SwooleBundle\SwooleBundle\Bridge\Tideways\Apm\TidewaysMiddlewareFactory;
use SwooleBundle\SwooleBundle\Bridge\Tideways\Apm\WithApm;
use SwooleBundle\SwooleBundle\Bridge\Upscale\Blackfire\Profiling\ProfilerActivator;
use SwooleBundle\SwooleBundle\Bridge\Upscale\Blackfire\Profiling\UpscaleProfilerActivator;
use SwooleBundle\SwooleBundle\Bridge\Upscale\Blackfire\Profiling\WithProfiler;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Server\Config\Socket;
use SwooleBundle\SwooleBundle\Server\Config\Sockets;
use SwooleBundle\SwooleBundle\Server\Configurator\Configurator;
use SwooleBundle\SwooleBundle\Server\Configurator\WithHealthEvaluatorProcess;
use SwooleBundle\SwooleBundle\Server\Configurator\WithHealthProcess;
use SwooleBundle\SwooleBundle\Server\Health\HealthCheck;
use SwooleBundle\SwooleBundle\Server\Health\HealthReporter;
use SwooleBundle\SwooleBundle\Server\Health\HealthStatusTable;
use SwooleBundle\SwooleBundle\Server\HttpServerConfiguration;
use SwooleBundle\SwooleBundle\Server\Middleware\MiddlewareInjector;
use SwooleBundle\SwooleBundle\Server\RequestHandler\AdvancedStaticFilesServer;
use SwooleBundle\SwooleBundle\Server\RequestHandler\ExceptionHandler\ExceptionHandler;
use SwooleBundle\SwooleBundle\Server\RequestHandler\ExceptionHandler\JsonExceptionHandler;
use SwooleBundle\SwooleBundle\Server\RequestHandler\ExceptionHandler\ProductionExceptionHandler;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandler;
use SwooleBundle\SwooleBundle\Server\Runtime\Bootable;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\ContainerFreshness;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\HotModuleReloader;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\HotModuleReloadTimer;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\InotifyHMR;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\NonReloadableCodeFreshness;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\NonReloadableFiles;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\RestartAwareHotModuleReloader;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\StatHMR;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandler;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\HMRWorkerExitHandler;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\HMRWorkerStartHandler;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerExitHandler;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerStartHandler;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Tideways\Profiler as TidewaysProfiler;
use Upscale\Swoole\Blackfire\Profiler as BlackfireProfiler;
use ZEngine\Core;

/**
 * @phpstan-type SwooleSettings = array{
 *   hook_flags?: int,
 *   max_coroutine?: int,
 *   fiber_context?: 'auto'|'off'|'on'
 * }
 * @phpstan-type HttpServerRuntimeConfig = array{
 *   serve_static: 'auto'|'off'|'advanced'|'default',
 *   public_dir: string,
 *   log_level: 'auto'|'debug'|'trace'|'info'|'notice'|'warning'|'error',
 *   enable_coroutine: bool,
 *   upload_tmp_dir: string,
 *   user: string,
 *   group: string,
 *   http_compression: bool,
 *   http_compression_level: int,
 * }
 * @phpstan-type TaskWorkerServicesConfig = array{
 *   reset_handler: bool,
 * }
 * @phpstan-type TaskWorkerConfig = array{
 *   services: TaskWorkerServicesConfig,
 *   settings: array{
 *     worker_count: int|null,
 *   },
 *   commands?: array<int, list<string>>,
 * }
 * @phpstan-type PlatformConfig = array{
 *   fiber_context: array{
 *     enabled: 'auto'|'off'|'on',
 *   },
 *   coroutines: array{
 *     enabled: bool,
 *     max_concurrency?: int|null,
 *     max_coroutines: int,
 *     max_service_instances?: int|null,
 *     stateful_services?: array<string>,
 *     compile_processors?: array<string>,
 *     doctrine_processor_config?: array<string, string>,
 *   },
 * }
 * @phpstan-type ServicesConfig = array{
 *   debug_handler: bool,
 *   cloudfront_proto_header_handler: bool,
 *   trust_all_proxies_handler: bool,
 *   blackfire_profiler: bool|null,
 *   blackfire_monitoring: bool|null,
 *   tideways_apm: array{
 *     enabled: bool,
 *     service_name: string,
 *   },
 *   access_log: array{
 *     enabled: bool,
 *     format: string|null,
 *     register_monolog_formatter_service: bool,
 *     monolog_formatter_service_name?: string,
 *     monolog_formatter_format?: string,
 *   },
 * }
 * @phpstan-type ExceptionHandlerConfig = array{
 *   handler_id: string,
 *   type: 'auto'|'json'|'symfony'|'custom'|'production',
 *   verbosity: 'auto'|'verbose'|'default'|'trace',
 * }
 * @phpstan-type HmrConfig = array{
 *   enabled: 'off'|'auto'|'inotify'|'stat'|'external',
 *   file_path?: string,
 * }
 * @phpstan-type HealthcheckConfig = array{
 *   enabled: bool,
 *   host: string,
 *   port: int,
 *   path: string,
 *   checks: array{
 *     interval: int,
 *     staleness_threshold: int,
 *   },
 * }
 * @phpstan-type HttpServerConfig = array{
 *   running_mode: string,
 *   api: array{
 *     enabled: bool,
 *     host: string,
 *     port: int,
 *   },
 *   healthcheck: HealthcheckConfig,
 *   hmr: HmrConfig,
 *   host: string,
 *   port: int,
 *   trusted_proxies: array<string>,
 *   trusted_hosts: array<string>,
 *   settings: HttpServerRuntimeConfig,
 *   socket_type: int,
 *   ssl_enabled: bool,
 *   static: array{
 *     strategy: 'auto'|'off'|'advanced'|'default',
 *     public_dir: string,
 *     mime_types: array<string, string>,
 *   },
 *   services: ServicesConfig,
 *   exception_handler: ExceptionHandlerConfig,
 * }
 * @phpstan-type SessionConfig = array{
 *   max_data_bytes: int,
 *   max_active_sessions: int,
 * }
 * @phpstan-type BundleConfig = array{
 *   http_server: HttpServerConfig,
 *   task_worker?: TaskWorkerConfig,
 *   platform?: PlatformConfig,
 *   session?: SessionConfig,
 * }
 */
final class SwooleExtension extends Extension
{
    /**
     * @param array<BundleConfig> $configs
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = Configuration::fromTreeBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');
        $loader->load('commands.php');

        $container->registerForAutoconfiguration(Bootable::class)
            ->addTag('swoole_bundle.bootable_service');
        $container->registerForAutoconfiguration(Configurator::class)
            ->addTag('swoole_bundle.server_configurator');
        $container->registerForAutoconfiguration(HealthCheck::class)
            ->addTag(ContainerConstants::TAG_HEALTH_CHECK);

        // Not tied to the task worker: a messenger worker keeps one coroutine - or none at all - across
        // every message it handles either way, so the pools need resetting between them wherever
        // messenger:consume is run from.
        if (class_exists(WorkerMessageReceivedEvent::class)) {
            $container->register(ResetServicePoolsBetweenMessages::class)
                ->setPublic(false)
                ->setAutowired(false)
                ->setAutoconfigured(false)
                ->setArgument('$servicePoolContainer', new Reference(ServicePoolContainer::class))
                ->addTag('kernel.event_subscriber');
        }

        /** @var BundleConfig $config */
        $config = $this->processConfiguration($configuration, $configs);

        $runningMode = $config['http_server']['running_mode'];

        $maxConcurrency = null;

        if (isset($config['platform']['coroutines']['max_concurrency'])) {
            $maxConcurrency = $config['platform']['coroutines']['max_concurrency'];
        }

        $fiberContext = 'auto';

        if (isset($config['platform']['fiber_context']['enabled'])) {
            $fiberContext = $config['platform']['fiber_context']['enabled'];
        }

        $swooleSettings = isset($config['platform'])
            ? $this->configurePlatform($config['platform'], $maxConcurrency, $container)
            : [];
        $swooleSettings += $this->configureHttpServer($config['http_server'], $container);
        $swooleSettings += isset($config['task_worker'])
            ? $this->configureTaskWorker($config['task_worker'], $container)
            : [];
        $this->configureSession($config['session'] ?? [], $container);
        $this->assignSwooleConfiguration($swooleSettings, $runningMode, $maxConcurrency, $fiberContext, $container);
    }

    #[Override]
    public function getAlias(): string
    {
        return 'swoole';
    }

    /**
     * @param BundleConfig $config
     */
    #[Override]
    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return Configuration::fromTreeBuilder();
    }

    /**
     * @param PlatformConfig $config
     * @return SwooleSettings
     */
    private function configurePlatform(array $config, ?int $maxConcurrency, ContainerBuilder $container): array
    {
        $swooleSettings = [];
        $coroutineSettings = $config['coroutines'];

        if (!$coroutineSettings['enabled']) {
            return $swooleSettings;
        }

        if (!class_exists(Core::class)) {
            throw new RuntimeException('Please install lisachenko/z-engine to use coroutines');
        }

        $swooleSettings['hook_flags'] = SystemSwooleFactory::newFactoryInstance()->newInstance()
            ->coroutineHookFlags();
        $swooleSettings['max_coroutine'] = $coroutineSettings['max_coroutines'];
        $container->setParameter(ContainerConstants::PARAM_COROUTINES_ENABLED, true);
        $maxServiceInstances = $maxConcurrency ?? $swooleSettings['max_coroutine'];

        if (isset($coroutineSettings['max_service_instances'])) {
            $maxServiceInstances = $coroutineSettings['max_service_instances'];
        }

        $container->setParameter(ContainerConstants::PARAM_COROUTINES_MAX_SVC_INSTANCES, $maxServiceInstances);

        if (isset($coroutineSettings['stateful_services'])) {
            $container->setParameter(
                ContainerConstants::PARAM_COROUTINES_STATEFUL_SERVICES,
                $coroutineSettings['stateful_services']
            );
        }

        if (isset($coroutineSettings['compile_processors'])) {
            $container->setParameter(
                ContainerConstants::PARAM_COROUTINES_COMPILE_PROCESSORS,
                $coroutineSettings['compile_processors']
            );
        }

        $container->registerForAutoconfiguration(StabilityChecker::class)
            ->addTag(ContainerConstants::TAG_STABILITY_CHECKER);

        if (isset($coroutineSettings['doctrine_processor_config'])) {
            $container->setParameter(
                ContainerConstants::PARAM_COROUTINES_DOCTRINE_COMPILE_PROCESSOR_CONFIG,
                $coroutineSettings['doctrine_processor_config']
            );
        }

        return $swooleSettings;
    }

    /**
     * @param HttpServerConfig $config
     * @return HttpServerRuntimeConfig
     * @throws ServiceNotFoundException
     */
    private function configureHttpServer(array $config, ContainerBuilder $container): array
    {
        $this->configureHttpServerServices($config['services'], $container);
        $this->configureExceptionHandler($config['exception_handler'], $container);

        $container->setParameter('swoole.http_server.trusted_proxies', $config['trusted_proxies']);
        $container->setParameter('swoole.http_server.trusted_hosts', $config['trusted_hosts']);
        $container->setParameter('swoole.http_server.api.host', $config['api']['host']);
        $container->setParameter('swoole.http_server.api.port', $config['api']['port']);

        return $this->prepareHttpServerConfiguration($config, $container);
    }

    /**
     * @param ExceptionHandlerConfig $config
     */
    private function configureExceptionHandler(array $config, ContainerBuilder $container): void
    {
        [
            'handler_id' => $handlerId,
            'type' => $type,
            'verbosity' => $verbosity,
        ] = $config;

        if ($type === 'auto') {
            $type = $this->isProd($container) ? 'production' : 'json';
        }

        switch ($type) {
            case 'json':
                $class = JsonExceptionHandler::class;

                break;
            case 'symfony':
                $this->configureSymfonyExceptionHandler($container);
                $class = SymfonyExceptionHandler::class;

                break;
            case 'custom':
                $class = $handlerId;

                break;
            default: // case 'production'
                $class = ProductionExceptionHandler::class;

                break;
        }

        $container->setAlias(ExceptionHandler::class, $class);

        if ($verbosity === 'auto') {
            if ($this->isProd($container)) {
                $verbosity = 'production';
            } elseif ($this->isDebug($container)) {
                $verbosity = 'trace';
            } else {
                $verbosity = 'verbose';
            }
        }

        $container->getDefinition(JsonExceptionHandler::class)
            ->setArgument('$verbosity', $verbosity);
    }

    /**
     * @param HttpServerConfig $config
     * @return HttpServerRuntimeConfig
     */
    private function prepareHttpServerConfiguration(array $config, ContainerBuilder $container): array
    {
        [
            'api' => $api,
            'healthcheck' => $healthcheck,
            'hmr' => $hmr,
            'host' => $host,
            'port' => $port,
            'settings' => $settings,
            'socket_type' => $socketType,
            'ssl_enabled' => $sslEnabled,
            'static' => $static,
        ] = $config;

        if ($static['strategy'] === 'auto') {
            $static['strategy'] = $this->isDebugOrNotProd($container) ? 'advanced' : 'off';
        }

        if ($static['strategy'] === 'advanced') {
            $mimeTypes = $static['mime_types'];
            $container->register(AdvancedStaticFilesServer::class)
                ->addArgument(new Reference(AdvancedStaticFilesServer::class . '.inner'))
                ->addArgument(new Reference(HttpServerConfiguration::class))
                ->addArgument($mimeTypes)
                ->addTag('swoole_bundle.bootable_service')
                ->setDecoratedService(RequestHandler::class, null, -60);
        }

        $settings['serve_static'] = $static['strategy'];
        $settings['public_dir'] = $static['public_dir'];

        if ($settings['log_level'] === 'auto') {
            $settings['log_level'] = $this->isDebug($container) ? 'debug' : 'notice';
        }

        if ((bool) $container->getParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
            $settings['enable_coroutine'] = true;

            $kernelProxy = $container->findDefinition('kernel_proxy');
            $kernelProxy->setPublic(true);

            $requestHandler = $container->findDefinition(HttpKernelRequestHandler::class);
            $requestHandler->setArgument('$kernel', new Reference('kernel_proxy'));

            $coroutineKernelHandler = $container->findDefinition(ContextReleasingHttpKernelRequestHandler::class);
            $coroutineKernelHandler->setArgument(
                '$decorated',
                new Reference(ContextReleasingHttpKernelRequestHandler::class . '.inner')
            );
            $coroutineKernelHandler->setDecoratedService(RequestHandler::class, null, -1000);

            if ($this->isDebug($container) && PHP_OS_FAMILY === 'Darwin') {
                $container->register(DebugClassLoaderOverridingWorkerStartHandler::class)
                    ->setDecoratedService(WorkerStartHandler::class, null, -100)
                    ->setArgument(
                        '$decorated',
                        new Reference(DebugClassLoaderOverridingWorkerStartHandler::class . '.inner'),
                    );
            }
        }

        if ($hmr['enabled'] === 'auto') {
            $hmr['enabled'] = $this->resolveAutoHMR();
        }

        $sockets = $container->getDefinition(Sockets::class)
            ->addArgument(new Definition(Socket::class, [$host, $port, $socketType, $sslEnabled]));

        if ($api['enabled']) {
            $sockets->addArgument(new Definition(Socket::class, [$api['host'], $api['port']]));
        }

        if ($healthcheck['enabled']) {
            $this->configureHttpServerHealthcheck($healthcheck, $container);
        }

        $this->configureHttpServerHMR($hmr, $container);

        return $settings;
    }

    /**
     * @param HealthcheckConfig $healthcheck
     */
    private function configureHttpServerHealthcheck(array $healthcheck, ContainerBuilder $container): void
    {
        $container->register(HealthStatusTable::class)
            ->setFactory([HealthStatusTable::class, 'forChecks'])
            ->setArgument('$checkCount', 0);

        $container->register(HealthReporter::class)
            ->setArgument('$table', new Reference(HealthStatusTable::class))
            ->setArgument('$stalenessThreshold', $healthcheck['checks']['staleness_threshold']);

        $container->register(WithHealthEvaluatorProcess::class)
            ->setArgument('$table', new Reference(HealthStatusTable::class))
            ->setArgument('$checks', new TaggedIteratorArgument(ContainerConstants::TAG_HEALTH_CHECK))
            ->setArgument('$interval', $healthcheck['checks']['interval'])
            ->addTag('swoole_bundle.server_configurator');

        $container->register(WithHealthProcess::class)
            ->setArgument('$socket', new Definition(Socket::class, [$healthcheck['host'], $healthcheck['port']]))
            ->setArgument('$reporter', new Reference(HealthReporter::class))
            ->setArgument('$path', $healthcheck['path'])
            ->addTag('swoole_bundle.server_configurator');
    }

    /**
     * @param HmrConfig $hmr
     */
    private function configureHttpServerHMR(array $hmr, ContainerBuilder $container): void
    {
        if ($hmr['enabled'] === 'off' || !$this->isDebug($container)) {
            return;
        }

        if ($hmr['enabled'] === 'external') {
            $container->register(NonReloadableFiles::class)
                ->addTag('swoole_bundle.bootable_service')
                ->setArgument('$kernelCacheDir', $container->getParameter('kernel.cache_dir'))
                ->setArgument('$filePathDir', $hmr['file_path'] ?? $container->getParameter('swoole_bundle.cache_dir'))
                ->setArgument('$fileSystem', new Reference(Filesystem::class));

            return;
        }

        // The reloader itself, under its own id: what HotModuleReloader resolves to is the wrapper
        // below, so that everything asking for one gets the container check in front of it.
        if ($hmr['enabled'] === 'inotify') {
            $container->register(InotifyHMR::class)
                ->addTag('swoole_bundle.bootable_service');
            $watcher = InotifyHMR::class;
        }

        if ($hmr['enabled'] === 'stat') {
            $container->register(StatHMR::class)
                ->addTag('swoole_bundle.bootable_service')
                ->setArgument('$kernelCacheDir', $container->getParameter('kernel.cache_dir'));
            $watcher = StatHMR::class;
        }

        if (!isset($watcher)) {
            return;
        }

        // Booted in the master, which is the only place its answer is right - it has to be the set of
        // files loaded before the fork.
        $container->register(NonReloadableCodeFreshness::class)
            ->setPublic(false)
            ->setAutoconfigured(false)
            ->addTag('swoole_bundle.bootable_service')
            ->setArgument('$kernelCacheDir', $container->getParameter('kernel.cache_dir'));

        $container->register(HotModuleReloader::class, RestartAwareHotModuleReloader::class)
            ->setPublic(false)
            ->setAutoconfigured(false)
            ->setArgument('$decorated', new Reference($watcher))
            ->setArgument('$conditions', [
                // The container first: a config change is the one a developer is most likely to be
                // waiting on, and naming it beats naming whichever file happened to be checked first.
                new Reference(ContainerFreshness::class),
                new Reference(NonReloadableCodeFreshness::class),
            ])
            ->setArgument('$logger', new Reference('logger'))
            ->addTag('monolog.logger', [
                'channel' => 'swoole',
            ]);

        $container->register(HotModuleReloadTimer::class)
            ->setPublic(false)
            ->setAutoconfigured(false)
            ->setArgument('$swoole', new Reference(Swoole::class));

        $container->register(HMRWorkerStartHandler::class)
            ->setPublic(false)
            ->setAutoconfigured(false)
            ->setArgument('$hmr', new Reference(HotModuleReloader::class))
            ->setArgument('$timer', new Reference(HotModuleReloadTimer::class))
            ->setArgument('$decorated', new Reference(HMRWorkerStartHandler::class . '.inner'))
            ->setDecoratedService(WorkerStartHandler::class);

        // Registered with the timer and not just alongside it: the worker that started the timer is the
        // one that has to stop it, and it has to do so from onWorkerExit or not at all.
        $container->register(HMRWorkerExitHandler::class)
            ->setPublic(false)
            ->setAutoconfigured(false)
            ->setArgument('$timer', new Reference(HotModuleReloadTimer::class))
            ->setArgument('$decorated', new Reference(HMRWorkerExitHandler::class . '.inner'))
            ->setDecoratedService(WorkerExitHandler::class);
    }

    /**
     * @return 'inotify'|'stat'
     */
    private function resolveAutoHMR(): string
    {
        return extension_loaded('inotify') ? 'inotify' : 'stat';
    }

    /**
     * Registers optional http server dependencies providing various features.
     *
     * @param ServicesConfig $config
     */
    private function configureHttpServerServices(array $config, ContainerBuilder $container): void
    {
        // RequestFactoryInterface
        // -----------------------
        if ($config['cloudfront_proto_header_handler']) {
            $container->register(CloudFrontRequestFactory::class)
                ->addArgument(new Reference(CloudFrontRequestFactory::class . '.inner'))
                ->setAutowired(false)
                ->setAutoconfigured(false)
                ->setPublic(false)
                ->setDecoratedService(RequestFactory::class, null, -10);
        }

        // RequestHandlerInterface
        // -------------------------
        if ($config['trust_all_proxies_handler']) {
            $container->register(TrustAllProxiesRequestHandler::class)
                ->addArgument(new Reference(TrustAllProxiesRequestHandler::class . '.inner'))
                ->addTag('swoole_bundle.bootable_service')
                ->setDecoratedService(RequestHandler::class, null, -10);
        }

        if ($config['blackfire_profiler'] && class_exists(BlackfireProfiler::class)) {
            $container->register(BlackfireProfiler::class)
                ->setClass(BlackfireProfiler::class);

            $container->register(ProfilerActivator::class)
                ->setClass(UpscaleProfilerActivator::class)
                ->setAutowired(false)
                ->setAutoconfigured(false)
                ->setPublic(false)
                ->addArgument(new Reference(BlackfireProfiler::class));

            $container->register(WithProfiler::class)
                ->setClass(WithProfiler::class)
                ->setAutowired(false)
                ->setAutoconfigured(false)
                ->setPublic(false)
                ->addArgument(new Reference(ProfilerActivator::class));
            $def = $container->getDefinition('swoole_bundle.server.http_server.configurator.for_server_run_command');
            $def->addArgument(new Reference(WithProfiler::class));
            $def = $container->getDefinition('swoole_bundle.server.http_server.configurator.for_server_start_command');
            $def->addArgument(new Reference(WithProfiler::class));
        }

        if ($config['blackfire_monitoring'] && InstalledVersions::isInstalled('blackfire/php-sdk')) {
            $container->setParameter(ContainerConstants::PARAM_BLACKFIRE_MONITORING_ENABLED, true);
        }

        if ($config['tideways_apm']['enabled'] && class_exists(TidewaysProfiler::class)) {
            $container->register(RequestDataProvider::class)
                ->setClass(RequestDataProvider::class)
                ->setAutowired(false)
                ->setAutoconfigured(false)
                ->setPublic(false)
                ->setArgument('$requestFactory', new Reference(RequestFactory::class));

            $container->register(RequestProfiler::class)
                ->setClass(RequestProfiler::class)
                ->setAutowired(false)
                ->setAutoconfigured(false)
                ->setPublic(false)
                ->setArgument('$dataProvider', new Reference(RequestDataProvider::class))
                ->setArgument('$serviceName', $config['tideways_apm']['service_name']);

            $container->register(TidewaysMiddlewareFactory::class)
                ->setClass(TidewaysMiddlewareFactory::class)
                ->setAutowired(false)
                ->setAutoconfigured(false)
                ->setPublic(false)
                ->setArgument('$profiler', new Reference(RequestProfiler::class));

            $container->register(Apm::class)
                ->setClass(Apm::class)
                ->setAutowired(false)
                ->setAutoconfigured(false)
                ->setPublic(false)
                ->setArgument('$injector', new Reference(MiddlewareInjector::class))
                ->setArgument('$middlewareFactory', new Reference(TidewaysMiddlewareFactory::class));

            $container->register(WithApm::class)
                ->setClass(WithApm::class)
                ->setAutowired(false)
                ->setAutoconfigured(false)
                ->setPublic(false)
                ->setArgument('$apm', new Reference(Apm::class));
            $def = $container->getDefinition('swoole_bundle.server.http_server.configurator.for_server_run_command');
            $def->addArgument(new Reference(WithApm::class));
            $def = $container->getDefinition('swoole_bundle.server.http_server.configurator.for_server_start_command');
            $def->addArgument(new Reference(WithApm::class));
        }

        if (!$config['access_log']['enabled']) {
            return;
        }

        $accessLogFormatter = $container->register(AccessLogFormatter::class)
            ->setClass(SimpleAccessLogFormatter::class)
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setPublic(false);

        if ($config['access_log']['format'] !== null) {
            $accessLogFormatter->setArgument('$format', $config['access_log']['format']);
        }

        $container->register(AccessLogOnKernelTerminate::class)
            ->setClass(AccessLogOnKernelTerminate::class)
            ->addTag('kernel.event_subscriber')
            ->addTag('monolog.logger', ['channel' => 'swoole.access_log'])
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setPublic(false)
            ->setArgument('$formatter', new Reference(AccessLogFormatter::class));

        if (!$config['access_log']['register_monolog_formatter_service']) {
            return;
        }

        $lineFormatterServiceName = 'monolog.formatter.line.swoole.access_log';
        if (isset($config['access_log']['monolog_formatter_service_name'])) {
            $lineFormatterServiceName = $config['access_log']['monolog_formatter_service_name'];
        }
        $lineFormatterFormat = "%%message%% %%context%% %%extra%%\n";
        if (isset($config['access_log']['monolog_formatter_format'])) {
            $lineFormatterFormat = $config['access_log']['monolog_formatter_format'];
        }
        $container->register($lineFormatterServiceName)
            ->setClass(LineFormatter::class)
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setPublic(false)
            ->setArgument('$format', $lineFormatterFormat);
    }

    private function configureSymfonyExceptionHandler(ContainerBuilder $container): void
    {
        if (!class_exists(ErrorHandler::class)) {
            throw new RuntimeException(
                'To be able to use Symfony exception handler, '
                . 'the "symfony/error-handler" package needs to be installed.'
            );
        }

        $container->register('swoole_bundle.error_handler.resetter', ErrorHandlerResetter::class)
            ->setPublic(false);
        $container->register('swoole_bundle.error_handler.symfony_error_handler', ErrorHandler::class)
            ->setPublic(true)
            // the handler is pooled per coroutine, but ErrorHandler::handleException() leaves a
            // `[$this, 'renderException']` behind in $exceptionHandler which makes the next request's
            // proxied setExceptionHandler() call fatal - see ErrorHandlerResetter
            ->addTag(ContainerConstants::TAG_STATEFUL_SERVICE, [
                'resetter' => 'swoole_bundle.error_handler.resetter',
            ]);
        $container->register(ThrowableHandlerFactory::class)
            ->setPublic(false);
        $container->register('swoole_bundle.error_handler.symfony_kernel_throwable_handler', ReflectionMethod::class)
            ->setFactory([ThrowableHandlerFactory::class, 'newThrowableHandler'])
            ->setPublic(false);
        $container->register(ExceptionHandlerFactory::class)
            ->setArgument('$kernel', new Reference('http_kernel')) // @todo check if this is ok with coroutines enabled
            ->setArgument(
                '$throwableHandler',
                new Reference('swoole_bundle.error_handler.symfony_kernel_throwable_handler')
            )
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setPublic(false);
        $container->register(ErrorResponder::class)
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setPublic(false)
            ->setArgument('$errorHandler', new Reference('swoole_bundle.error_handler.symfony_error_handler'))
            ->setArgument('$handlerFactory', new Reference(ExceptionHandlerFactory::class));
        $container->register(SymfonyExceptionHandler::class)
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setPublic(false)
            ->setArgument('$kernel', new Reference('http_kernel')) // @todo check if this is ok with coroutines enabled
            ->setArgument('$requestFactory', new Reference(RequestFactory::class))
            ->setArgument('$responseProcessor', new Reference(ResponseProcessor::class))
            ->setArgument('$errorResponder', new Reference(ErrorResponder::class));
    }

    /**
     * @param TaskWorkerConfig $config
     * @return SwooleSettings
     */
    private function configureTaskWorker(array $config, ContainerBuilder $container): array
    {
        $commandGroups = array_values($config['commands'] ?? []);
        $workerCount = $config['settings']['worker_count'] ?? null;
        $coroutinesEnabled = (bool) $container->getParameter(ContainerConstants::PARAM_COROUTINES_ENABLED);

        if ($commandGroups !== []) {
            // Commands imply the workers to run them on, so a worker_count left unset is filled in
            // rather than treated as "no task workers wanted" - which is what the check below would
            // otherwise make of it, silently dropping every configured command.
            $workerCount = $this->configureTaskWorkerCommands(
                $commandGroups,
                $workerCount,
                $coroutinesEnabled,
                $container,
            );
        }

        if ($workerCount === null) {
            return [];
        }

        $settings = [];
        $settings['task_worker_count'] = $workerCount;
        $settings['task_use_object'] = true;
        $this->configureTaskWorkerServices($config['services'], $container);

        if ($coroutinesEnabled) {
            $settings['task_enable_coroutine'] = true;
        }

        return $settings;
    }

    /**
     * EXPERIMENTAL. Registers the long running console command machinery.
     *
     * @param list<list<string>> $commandGroups one group per task worker
     * @return int the task worker count the configured groups need
     * @see docs/swoole-task-worker-commands.md
     */
    private function configureTaskWorkerCommands(
        array $commandGroups,
        ?int $workerCount,
        bool $coroutinesEnabled,
        ContainerBuilder $container,
    ): int {
        $this->assertCommandGroupsRunnable($commandGroups, $coroutinesEnabled);

        $required = count($commandGroups);
        $workerCount ??= $required;

        if ($workerCount < $required) {
            throw new InvalidConfigurationException(sprintf(
                'swoole.task_worker.commands configures %d task worker(s) but '
                . 'swoole.task_worker.settings.worker_count is %d. Every command group needs a task '
                . 'worker of its own, so raise worker_count to at least %d or remove some groups.',
                $required,
                $workerCount,
                $required,
            ));
        }

        $container->register(WorkerStopSignal::class)
            ->setPublic(false)
            ->setAutowired(false)
            ->setAutoconfigured(false);

        $container->register(WorkerRetirement::class)
            ->setPublic(false)
            ->setAutowired(false)
            ->setAutoconfigured(false);

        $container->register(WithWorkerStopSignal::class)
            ->setPublic(false)
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArgument('$stopSignal', new Reference(WorkerStopSignal::class))
            ->addTag('swoole_bundle.server_configurator');

        $container->register(RaiseStopSignalOnWorkerShutdown::class)
            ->setPublic(false)
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArgument('$stopSignal', new Reference(WorkerStopSignal::class))
            ->setArgument('$retirement', new Reference(WorkerRetirement::class))
            ->addTag('kernel.event_subscriber');

        // Caught here rather than when a worker tries to resolve its first command line: without
        // framework-bundle there is no console Application to run anything through, and a container
        // that compiles only to fail in every task worker is worse than one that refuses to compile.
        if (!class_exists(Application::class)) {
            throw CommandNotRunnable::consoleUnavailable();
        }

        // Shared, one per worker. Not pooled: an Application per coroutine would have each of them
        // reach the command loader, and ServiceLocatorTrait::get() reads its re-entry guard as a
        // circular reference when a second coroutine arrives while the first is suspended in the
        // factory. Shared keeps that path single-file - only the first resolve consults the loader,
        // every later one finds the command memoized.
        $container->register(TaskWorkerCommands::APPLICATION_SERVICE_ID, Application::class)
            ->setPublic(false)
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArgument('$kernel', new Reference('kernel'))
            // The worker owns the process lifetime, not the command: a command that finished is a
            // worker to be recycled, decided by CommandGroupRunner, and never a process that exits
            // from under swoole.
            ->addMethodCall('setAutoExit', [false])
            // Failures have to come back as throwables so they can be logged with the command they
            // came from and counted as a crashed worker, rather than rendered to stdout and swallowed.
            ->addMethodCall('setCatchExceptions', [false])
            ->addMethodCall('setDispatcher', [new Reference('event_dispatcher')]);

        $container->register(ApplicationCommandResolver::class)
            ->setPublic(false)
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArgument('$application', new Reference(TaskWorkerCommands::APPLICATION_SERVICE_ID));

        $container->register(StreamCommandOutputFactory::class)
            ->setPublic(false)
            ->setAutowired(false)
            ->setAutoconfigured(false);

        $container->register(CommandGroupRunner::class)
            ->setPublic(false)
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArgument('$resolver', new Reference(ApplicationCommandResolver::class))
            ->setArgument('$outputFactory', new Reference(StreamCommandOutputFactory::class))
            ->setArgument('$stopSignal', new Reference(WorkerStopSignal::class))
            ->setArgument('$retirement', new Reference(WorkerRetirement::class))
            ->setArgument('$swoole', new Reference(Swoole::class))
            ->setArgument('$logger', new Reference('logger'))
            ->addTag('monolog.logger', [
                'channel' => 'swoole',
            ]);

        $container->register(TaskWorkerCommands::class)
            ->setPublic(false)
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArgument('$groups', $commandGroups);

        $container->register(LongRunningCommandsWorkerStartHandler::class)
            ->setPublic(false)
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArgument('$commands', new Reference(TaskWorkerCommands::class))
            ->setArgument('$runner', new Reference(CommandGroupRunner::class))
            ->setArgument('$stopSignal', new Reference(WorkerStopSignal::class))
            ->setArgument('$coroutinesEnabled', $coroutinesEnabled)
            ->setArgument('$decorated', new Reference(LongRunningCommandsWorkerStartHandler::class . '.inner'))
            // Lower priority than HMR's decorator puts this one outermost, so the handlers it wraps have
            // all run by the time a blocking command takes the process over and never returns.
            ->setDecoratedService(WorkerStartHandler::class, null, -10);

        if (class_exists(WorkerRunningEvent::class)) {
            $container->register(StopMessengerWorkerOnShutdown::class)
                ->setPublic(false)
                ->setAutowired(false)
                ->setAutoconfigured(false)
                ->setArgument('$stopSignal', new Reference(WorkerStopSignal::class))
                ->addTag('kernel.event_subscriber');
        }

        return $workerCount;
    }

    /**
     * @param list<list<string>> $commandGroups
     */
    private function assertCommandGroupsRunnable(array $commandGroups, bool $coroutinesEnabled): void
    {
        foreach ($commandGroups as $index => $group) {
            if ($group === []) {
                throw new InvalidConfigurationException(sprintf(
                    'swoole.task_worker.commands group #%d is empty. Every group must name at least one '
                    . 'command, since a group is what claims a task worker.',
                    $index,
                ));
            }

            if ($coroutinesEnabled || count($group) === 1) {
                continue;
            }

            // Without coroutines there is no scheduler to spawn into: the one command blocks
            // onWorkerStart and owns the process, so a second command in the same group could never
            // start. Rejecting it here beats a task worker that silently runs only its first command.
            throw new InvalidConfigurationException(sprintf(
                'swoole.task_worker.commands group #%d lists %d commands to share one task worker, '
                . 'which needs platform.coroutines.enabled to be true. With coroutines off a task '
                . 'worker can run a single command; split them into %d separate groups instead.',
                $index,
                count($group),
                count($group),
            ));
        }
    }

    /**
     * @param TaskWorkerServicesConfig $config
     */
    private function configureTaskWorkerServices(array $config, ContainerBuilder $container): void
    {
        $loggingHandler = $container->findDefinition(ExceptionLoggingTransportHandler::class);
        $loggingHandler->setArgument(
            '$decorated',
            new Reference(ExceptionLoggingTransportHandler::class . '.inner')
        );
        $loggingHandler->setDecoratedService(TaskHandler::class, null, -9998);

        if (!$config['reset_handler']) {
            return;
        }

        $resetHandler = $container->findDefinition(ServiceResettingTransportHandler::class);
        $resetHandler->setArgument(
            '$decorated',
            new Reference(ServiceResettingTransportHandler::class . '.inner')
        );
        $resetHandler->setDecoratedService(TaskHandler::class, null, -9997);
    }

    /**
     * @param SwooleSettings $swooleSettings
     */
    private function assignSwooleConfiguration(
        array $swooleSettings,
        string $runningMode,
        ?int $maxConcurrency,
        string $fiberContext,
        ContainerBuilder $container,
    ): void {
        $container->getDefinition(HttpServerConfiguration::class)
            ->addArgument(new Reference(Swoole::class))
            ->addArgument(new Reference(Sockets::class))
            ->addArgument($runningMode)
            ->addArgument($swooleSettings)
            ->addArgument($maxConcurrency)
            ->addArgument($fiberContext);
    }

    /**
     * @param array<never, never>|SessionConfig $config
     */
    private function configureSession(array $config, ContainerBuilder $container): void
    {
        $container->setParameter('swoole_bundle.session.max_data_bytes', $config['max_data_bytes'] ?? 4096);
        $container->setParameter('swoole_bundle.session.max_active_sessions', $config['max_active_sessions'] ?? 1024);
    }

    private function isProd(ContainerBuilder $container): bool
    {
        return $container->getParameter('kernel.environment') === 'prod';
    }

    private function isDebug(ContainerBuilder $container): bool
    {
        return (bool) $container->getParameter('kernel.debug');
    }

    private function isDebugOrNotProd(ContainerBuilder $container): bool
    {
        return $this->isDebug($container) || !$this->isProd($container);
    }
}
