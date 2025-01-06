<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection;

use BlackfireProbe;
use Exception;
use Monolog\Formatter\LineFormatter;
use ReflectionMethod;
use RuntimeException;
use SwooleBundle\SwooleBundle\Bridge\Log\AccessLogFormatter;
use SwooleBundle\SwooleBundle\Bridge\Log\SimpleAccessLogFormatter;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\StabilityChecker;
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
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel\CoroutineKernelPool;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel\KernelPool;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\ExceptionLoggingTransportHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\ServiceResettingTransportHandler;
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
use SwooleBundle\SwooleBundle\Server\HttpServerConfiguration;
use SwooleBundle\SwooleBundle\Server\Middleware\MiddlewareInjector;
use SwooleBundle\SwooleBundle\Server\RequestHandler\AdvancedStaticFilesServer;
use SwooleBundle\SwooleBundle\Server\RequestHandler\ExceptionHandler\ExceptionHandler;
use SwooleBundle\SwooleBundle\Server\RequestHandler\ExceptionHandler\JsonExceptionHandler;
use SwooleBundle\SwooleBundle\Server\RequestHandler\ExceptionHandler\ProductionExceptionHandler;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandler;
use SwooleBundle\SwooleBundle\Server\Runtime\Bootable;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\HotModuleReloader;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\InotifyHMR;
use SwooleBundle\SwooleBundle\Server\Runtime\HMR\NonReloadableFiles;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandler;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\HMRWorkerStartHandler;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerStartHandler;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Tideways\Profiler as TidewaysProfiler;
use Upscale\Swoole\Blackfire\Profiler as BlackfireProfiler;
use ZEngine\Core;

/**
 * @phpstan-type SwooleSettings = array{
 *   hook_flags?: int,
 *   max_coroutine?: int,
 * }
 * @phpstan-type HttpServerRuntimeConfig = array{
 *   serve_static: 'auto'|'off'|'advanced'|'default',
 *   public_dir: string,
 *   log_level: 'auto'|'debug'|'trace'|'info'|'notice'|'warning'|'error',
 *   enable_coroutine: bool,
 *   upload_tmp_dir: string,
 *   user: string,
 *   group: string,
 * }
 * @phpstan-type TaskWorkerServicesConfig = array{
 *   reset_handler: bool,
 * }
 * @phpstan-type TaskWorkerConfig = array{
 *   services: TaskWorkerServicesConfig,
 *   settings: array{
 *     worker_count: int|null,
 *   },
 * }
 * @phpstan-type PlatformConfig = array{
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
 *   enabled: 'off'|'auto'|'inotify'|'external',
 *   file_path?: string,
 * }
 * @phpstan-type HttpServerConfig = array{
 *   running_mode: string,
 *   api: array{
 *     enabled: bool,
 *     host: string,
 *     port: int,
 *   },
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
 * @phpstan-type BundleConfig = array{
 *   http_server: HttpServerConfig,
 *   task_worker?: TaskWorkerConfig|null,
 *   platform?: PlatformConfig|null,
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

        /** @var BundleConfig $config */
        $config = $this->processConfiguration($configuration, $configs);

        $runningMode = $config['http_server']['running_mode'];

        $maxConcurrency = null;

        if (isset($config['platform']['coroutines']['max_concurrency'])) {
            $maxConcurrency = $config['platform']['coroutines']['max_concurrency'];
        }

        $swooleSettings = isset($config['platform'])
            ? $this->configurePlatform($config['platform'], $maxConcurrency, $container)
            : [];
        $swooleSettings += $this->configureHttpServer($config['http_server'], $container);
        $swooleSettings += isset($config['task_worker'])
            ? $this->configureTaskWorker($config['task_worker'], $container)
            : [];
        $this->assignSwooleConfiguration($swooleSettings, $runningMode, $maxConcurrency, $container);
    }

    public function getAlias(): string
    {
        return 'swoole';
    }

    /**
     * @param BundleConfig $config
     */
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

        $swooleSettings['hook_flags'] = SWOOLE_HOOK_ALL;
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
            $coroutineKernelHandler = $container->findDefinition(ContextReleasingHttpKernelRequestHandler::class);
            $coroutineKernelHandler->setArgument(
                '$decorated',
                new Reference(ContextReleasingHttpKernelRequestHandler::class . '.inner')
            );
            $coroutineKernelHandler->setDecoratedService(RequestHandler::class, null, -1000);

            $container->setAlias(KernelPool::class, CoroutineKernelPool::class);
        }

        if ($hmr['enabled'] === 'auto') {
            $hmr['enabled'] = $this->resolveAutoHMR();
        }

        $sockets = $container->getDefinition(Sockets::class)
            ->addArgument(new Definition(Socket::class, [$host, $port, $socketType, $sslEnabled]));

        if ($api['enabled']) {
            $sockets->addArgument(new Definition(Socket::class, [$api['host'], $api['port']]));
        }

        $this->configureHttpServerHMR($hmr, $container);

        return $settings;
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

        if ($hmr['enabled'] === 'inotify') {
            $container->register(HotModuleReloader::class, InotifyHMR::class)
                ->addTag('swoole_bundle.bootable_service');
        }

        $container->register(HMRWorkerStartHandler::class)
            ->setPublic(false)
            ->setAutoconfigured(false)
            ->setArgument('$hmr', new Reference(HotModuleReloader::class))
            ->setArgument('$swoole', new Reference(Swoole::class))
            ->setArgument('$decorated', new Reference(HMRWorkerStartHandler::class . '.inner'))
            ->setDecoratedService(WorkerStartHandler::class);
    }

    /**
     * @return 'inotify'|'off'
     */
    private function resolveAutoHMR(): string
    {
        if (extension_loaded('inotify')) {
            return 'inotify';
        }

        return 'off';
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

        if (
            $config['blackfire_profiler']
            || (
                $config['blackfire_profiler'] === false
                && class_exists(BlackfireProfiler::class)
            )
        ) {
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

        if ($config['blackfire_monitoring'] && class_exists(BlackfireProbe::class)) {
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

        $container->register('swoole_bundle.error_handler.symfony_error_handler', ErrorHandler::class)
            ->setPublic(false);
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
            ->setArgument('$errorHandler', new Reference('swoole_bundle.error_handler.symfony_error_handler'))
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
        if (!isset($config['settings']['worker_count'])) {
            return [];
        }

        $settings = [];
        $settings['task_worker_count'] = $config['settings']['worker_count'];
        $settings['task_use_object'] = true;
        $this->configureTaskWorkerServices($config['services'], $container);

        if ((bool) $container->getParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
            $settings['task_enable_coroutine'] = true;
        }

        return $settings;
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
        ContainerBuilder $container,
    ): void {
        $container->getDefinition(HttpServerConfiguration::class)
            ->addArgument(new Reference(Swoole::class))
            ->addArgument(new Reference(Sockets::class))
            ->addArgument($runningMode)
            ->addArgument($swooleSettings)
            ->addArgument($maxConcurrency);
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
