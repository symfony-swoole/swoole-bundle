<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection;

use K911\Swoole\Bridge\Symfony\Container\StabilityChecker;
use K911\Swoole\Bridge\Symfony\ErrorHandler\ErrorResponder;
use K911\Swoole\Bridge\Symfony\ErrorHandler\ExceptionHandlerFactory;
use K911\Swoole\Bridge\Symfony\ErrorHandler\SymfonyExceptionHandler;
use K911\Swoole\Bridge\Symfony\ErrorHandler\ThrowableHandlerFactory;
use K911\Swoole\Bridge\Symfony\HttpFoundation\CloudFrontRequestFactory;
use K911\Swoole\Bridge\Symfony\HttpFoundation\RequestFactoryInterface;
use K911\Swoole\Bridge\Symfony\HttpFoundation\TrustAllProxiesRequestHandler;
use K911\Swoole\Bridge\Symfony\HttpKernel\ContextReleasingHttpKernelRequestHandler;
use K911\Swoole\Bridge\Symfony\HttpKernel\CoroutineKernelPool;
use K911\Swoole\Bridge\Symfony\HttpKernel\KernelPoolInterface;
use K911\Swoole\Bridge\Symfony\Messenger\ExceptionLoggingTransportHandler;
use K911\Swoole\Bridge\Symfony\Messenger\ServiceResettingTransportHandler;
use K911\Swoole\Bridge\Tideways\Apm\Apm;
use K911\Swoole\Bridge\Tideways\Apm\RequestDataProvider;
use K911\Swoole\Bridge\Tideways\Apm\RequestProfiler;
use K911\Swoole\Bridge\Tideways\Apm\TidewaysMiddlewareFactory;
use K911\Swoole\Bridge\Tideways\Apm\WithApm;
use K911\Swoole\Bridge\Upscale\Blackfire\Profiling\ProfilerActivator;
use K911\Swoole\Bridge\Upscale\Blackfire\Profiling\WithProfiler;
use K911\Swoole\Server\Config\Socket;
use K911\Swoole\Server\Config\Sockets;
use K911\Swoole\Server\Configurator\ConfiguratorInterface;
use K911\Swoole\Server\HttpServerConfiguration;
use K911\Swoole\Server\Middleware\MiddlewareInjector;
use K911\Swoole\Server\RequestHandler\AdvancedStaticFilesServer;
use K911\Swoole\Server\RequestHandler\ExceptionHandler\ExceptionHandlerInterface;
use K911\Swoole\Server\RequestHandler\ExceptionHandler\JsonExceptionHandler;
use K911\Swoole\Server\RequestHandler\ExceptionHandler\ProductionExceptionHandler;
use K911\Swoole\Server\RequestHandler\RequestHandlerInterface;
use K911\Swoole\Server\Runtime\BootableInterface;
use K911\Swoole\Server\Runtime\HMR\HotModuleReloaderInterface;
use K911\Swoole\Server\Runtime\HMR\InotifyHMR;
use K911\Swoole\Server\Runtime\HMR\NonReloadableFiles;
use K911\Swoole\Server\TaskHandler\TaskHandlerInterface;
use K911\Swoole\Server\WorkerHandler\HMRWorkerStartHandler;
use K911\Swoole\Server\WorkerHandler\WorkerStartHandlerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Tideways\Profiler as TidewaysProfiler;
use Upscale\Swoole\Blackfire\Profiler as BlackfireProfiler;
use ZEngine\Core;

final class SwooleExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function prepend(ContainerBuilder $container): void
    {
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = Configuration::fromTreeBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
        $loader->load('commands.yaml');

        $container->registerForAutoconfiguration(BootableInterface::class)
            ->addTag('swoole_bundle.bootable_service')
        ;
        $container->registerForAutoconfiguration(ConfiguratorInterface::class)
            ->addTag('swoole_bundle.server_configurator')
        ;

        $config = $this->processConfiguration($configuration, $configs);

        $runningMode = $config['http_server']['running_mode'];

        $maxConcurrency = null;

        if (
            isset($config['platform']['coroutines']['max_concurrency'])
            && is_int($config['platform']['coroutines']['max_concurrency'])
        ) {
            $maxConcurrency = $config['platform']['coroutines']['max_concurrency'];
        }

        $swooleSettings = isset($config['platform']) ?
            $this->configurePlatform($config['platform'], $maxConcurrency, $container) : [];
        $swooleSettings += $this->configureHttpServer($config['http_server'], $container);
        $swooleSettings += isset($config['task_worker']) ?
            $this->configureTaskWorker($config['task_worker'], $container) : [];
        $this->assignSwooleConfiguration($swooleSettings, $runningMode, $maxConcurrency, $container);
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'swoole';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return Configuration::fromTreeBuilder();
    }

    private function configurePlatform(array $config, ?int $maxConcurrency, ContainerBuilder $container): array
    {
        $swooleSettings = [];
        $coroutineSettings = $config['coroutines'];

        if (!$coroutineSettings['enabled']) {
            return $swooleSettings;
        }

        if (!class_exists(Core::class)) {
            throw new \RuntimeException('Please install lisachenko/z-engine to use coroutines');
        }

        $swooleSettings['hook_flags'] = \SWOOLE_HOOK_ALL;
        $swooleSettings['max_coroutine'] = $coroutineSettings['max_coroutines'];
        $container->setParameter(ContainerConstants::PARAM_COROUTINES_ENABLED, true);
        $maxServiceInstances = $maxConcurrency ?? $swooleSettings['max_coroutine'];

        if (isset($coroutineSettings['max_service_instances']) && is_int($coroutineSettings['max_service_instances'])) {
            $maxServiceInstances = $coroutineSettings['max_service_instances'];
        }

        $container->setParameter(ContainerConstants::PARAM_COROUTINES_MAX_SVC_INSTANCES, $maxServiceInstances);

        if (isset($coroutineSettings['stateful_services']) && is_array($coroutineSettings['stateful_services'])) {
            $container->setParameter(
                ContainerConstants::PARAM_COROUTINES_STATEFUL_SERVICES,
                $coroutineSettings['stateful_services']
            );
        }

        if (isset($coroutineSettings['compile_processors']) && is_array($coroutineSettings['compile_processors'])) {
            $container->setParameter(
                ContainerConstants::PARAM_COROUTINES_COMPILE_PROCESSORS,
                $coroutineSettings['compile_processors']
            );
        }

        $container->registerForAutoconfiguration(StabilityChecker::class)
            ->addTag(ContainerConstants::TAG_STABILITY_CHECKER)
        ;

        if (isset($coroutineSettings['doctrine_processor_config'])) {
            $container->setParameter(
                ContainerConstants::PARAM_COROUTINES_DOCTRINE_COMPILE_PROCESSOR_CONFIG,
                $coroutineSettings['doctrine_processor_config']
            );
        }

        return $swooleSettings;
    }

    /**
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
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

    private function configureExceptionHandler(array $config, ContainerBuilder $container): void
    {
        [
            'handler_id' => $handlerId,
            'type' => $type,
            'verbosity' => $verbosity,
        ] = $config;

        if ('auto' === $type) {
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

        $container->setAlias(ExceptionHandlerInterface::class, $class);

        if ('auto' === $verbosity) {
            if ($this->isProd($container)) {
                $verbosity = 'production';
            } elseif ($this->isDebug($container)) {
                $verbosity = 'trace';
            } else {
                $verbosity = 'verbose';
            }
        }

        $container->getDefinition(JsonExceptionHandler::class)
            ->setArgument('$verbosity', $verbosity)
        ;
    }

    private function prepareHttpServerConfiguration(array $config, ContainerBuilder $container): array
    {
        [
            'static' => $static,
            'api' => $api,
            'hmr' => $hmr,
            'host' => $host,
            'port' => $port,
            'socket_type' => $socketType,
            'ssl_enabled' => $sslEnabled,
            'settings' => $settings,
        ] = $config;

        if ('auto' === $static['strategy']) {
            $static['strategy'] = $this->isDebugOrNotProd($container) ? 'advanced' : 'off';
        }

        if ('advanced' === $static['strategy']) {
            $mimeTypes = $static['mime_types'];
            $container->register(AdvancedStaticFilesServer::class)
                ->addArgument(new Reference(AdvancedStaticFilesServer::class.'.inner'))
                ->addArgument(new Reference(HttpServerConfiguration::class))
                ->addArgument($mimeTypes)
                ->addTag('swoole_bundle.bootable_service')
                ->setDecoratedService(RequestHandlerInterface::class, null, -60)
            ;
        }

        $settings['serve_static'] = $static['strategy'];
        $settings['public_dir'] = $static['public_dir'];

        if ('auto' === $settings['log_level']) {
            $settings['log_level'] = $this->isDebug($container) ? 'debug' : 'notice';
        }

        if ((bool) $container->getParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
            $settings['enable_coroutine'] = true;
            $coroutineKernelHandler = $container->findDefinition(ContextReleasingHttpKernelRequestHandler::class);
            $coroutineKernelHandler->setArgument(
                '$decorated',
                new Reference(ContextReleasingHttpKernelRequestHandler::class.'.inner')
            );
            $coroutineKernelHandler->setDecoratedService(RequestHandlerInterface::class, null, -1000);

            $container->setAlias(KernelPoolInterface::class, CoroutineKernelPool::class);
        }

        if ('auto' === $hmr['enabled']) {
            $hmr['enabled'] = $this->resolveAutoHMR();
        }

        $sockets = $container->getDefinition(Sockets::class)
            ->addArgument(new Definition(Socket::class, [$host, $port, $socketType, $sslEnabled]))
        ;

        if ($api['enabled']) {
            $sockets->addArgument(new Definition(Socket::class, [$api['host'], $api['port']]));
        }

        $this->configureHttpServerHMR($hmr, $container);

        return $settings;
    }

    private function configureHttpServerHMR(array $hmr, ContainerBuilder $container): void
    {
        if ('off' === $hmr['enabled'] || !$this->isDebug($container)) {
            return;
        }

        if ('external' === $hmr['enabled']) {
            $container->register(NonReloadableFiles::class)
                ->addTag('swoole_bundle.bootable_service')
                ->setArgument('$kernelCacheDir', $container->getParameter('kernel.cache_dir'))
                ->setArgument('$filePathDir', $hmr['file_path'] ?? $container->getParameter('swoole_bundle.cache_dir'))
                ->setArgument('$fileSystem', new Reference(Filesystem::class))
            ;

            return;
        }

        if ('inotify' === $hmr['enabled']) {
            $container->register(HotModuleReloaderInterface::class, InotifyHMR::class)
                ->addTag('swoole_bundle.bootable_service')
            ;
        }

        $container->autowire(HMRWorkerStartHandler::class)
            ->setPublic(false)
            ->setAutoconfigured(true)
            ->setArgument('$decorated', new Reference(HMRWorkerStartHandler::class.'.inner'))
            ->setDecoratedService(WorkerStartHandlerInterface::class)
        ;
    }

    private function resolveAutoHMR(): string
    {
        if (\extension_loaded('inotify')) {
            return 'inotify';
        }

        return 'off';
    }

    /**
     * Registers optional http server dependencies providing various features.
     */
    private function configureHttpServerServices(array $config, ContainerBuilder $container): void
    {
        // RequestFactoryInterface
        // -----------------------
        if ($config['cloudfront_proto_header_handler']) {
            $container->register(CloudFrontRequestFactory::class)
                ->addArgument(new Reference(CloudFrontRequestFactory::class.'.inner'))
                ->setAutowired(true)
                ->setAutoconfigured(true)
                ->setPublic(false)
                ->setDecoratedService(RequestFactoryInterface::class, null, -10)
            ;
        }

        // RequestHandlerInterface
        // -------------------------
        if ($config['trust_all_proxies_handler']) {
            $container->register(TrustAllProxiesRequestHandler::class)
                ->addArgument(new Reference(TrustAllProxiesRequestHandler::class.'.inner'))
                ->addTag('swoole_bundle.bootable_service')
                ->setDecoratedService(RequestHandlerInterface::class, null, -10)
            ;
        }

        if ($config['blackfire_profiler'] || (null === $config['blackfire_profiler'] && \class_exists(BlackfireProfiler::class))) {
            $container->register(BlackfireProfiler::class)
                ->setClass(BlackfireProfiler::class)
            ;

            $container->register(ProfilerActivator::class)
                ->setClass(ProfilerActivator::class)
                ->setAutowired(false)
                ->setAutoconfigured(false)
                ->setPublic(false)
                ->addArgument(new Reference(BlackfireProfiler::class))
            ;

            $container->register(WithProfiler::class)
                ->setClass(WithProfiler::class)
                ->setAutowired(false)
                ->setAutoconfigured(false)
                ->setPublic(false)
                ->addArgument(new Reference(ProfilerActivator::class))
            ;
            $def = $container->getDefinition('swoole_bundle.server.http_server.configurator.for_server_run_command');
            $def->addArgument(new Reference(WithProfiler::class));
            $def = $container->getDefinition('swoole_bundle.server.http_server.configurator.for_server_start_command');
            $def->addArgument(new Reference(WithProfiler::class));
        }

        if ($config['blackfire_monitoring'] || (null === $config['blackfire_monitoring'] && \class_exists(\BlackfireProbe::class))) {
            $container->setParameter(ContainerConstants::PARAM_BLACKFIRE_MONITORING_ENABLED, true);
        }

        if ($config['tideways_apm']['enabled'] && \class_exists(TidewaysProfiler::class)) {
            $container->register(RequestDataProvider::class)
                ->setClass(RequestDataProvider::class)
                ->setAutowired(false)
                ->setAutoconfigured(false)
                ->setPublic(false)
                ->setArgument('$requestFactory', new Reference(RequestFactoryInterface::class))
            ;

            $container->register(RequestProfiler::class)
                ->setClass(RequestProfiler::class)
                ->setAutowired(false)
                ->setAutoconfigured(false)
                ->setPublic(false)
                ->setArgument('$dataProvider', new Reference(RequestDataProvider::class))
                ->setArgument('$serviceName', $config['tideways_apm']['service_name'])
            ;

            $container->register(TidewaysMiddlewareFactory::class)
                ->setClass(TidewaysMiddlewareFactory::class)
                ->setAutowired(false)
                ->setAutoconfigured(false)
                ->setPublic(false)
                ->setArgument('$profiler', new Reference(RequestProfiler::class))
            ;

            $container->register(Apm::class)
                ->setClass(Apm::class)
                ->setAutowired(false)
                ->setAutoconfigured(false)
                ->setPublic(false)
                ->setArgument('$injector', new Reference(MiddlewareInjector::class))
                ->setArgument('$middlewareFactory', new Reference(TidewaysMiddlewareFactory::class))
            ;

            $container->register(WithApm::class)
                ->setClass(WithApm::class)
                ->setAutowired(false)
                ->setAutoconfigured(false)
                ->setPublic(false)
                ->setArgument('$apm', new Reference(Apm::class))
            ;
            $def = $container->getDefinition('swoole_bundle.server.http_server.configurator.for_server_run_command');
            $def->addArgument(new Reference(WithApm::class));
            $def = $container->getDefinition('swoole_bundle.server.http_server.configurator.for_server_start_command');
            $def->addArgument(new Reference(WithApm::class));
        }
    }

    private function configureSymfonyExceptionHandler(ContainerBuilder $container): void
    {
        if (!\class_exists(ErrorHandler::class)) {
            throw new \RuntimeException('To be able to use Symfony exception handler, the "symfony/error-handler" package needs to be installed.');
        }

        $container->register('swoole_bundle.error_handler.symfony_error_handler', ErrorHandler::class)
            ->setPublic(false)
        ;
        $container->register(ThrowableHandlerFactory::class)
            ->setPublic(false)
        ;
        $container->register('swoole_bundle.error_handler.symfony_kernel_throwable_handler', \ReflectionMethod::class)
            ->setFactory([ThrowableHandlerFactory::class, 'newThrowableHandler'])
            ->setPublic(false)
        ;
        $container->register(ExceptionHandlerFactory::class)
            ->setArgument('$throwableHandler', new Reference('swoole_bundle.error_handler.symfony_kernel_throwable_handler'))
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setPublic(false)
        ;
        $container->register(ErrorResponder::class)
            ->setArgument('$errorHandler', new Reference('swoole_bundle.error_handler.symfony_error_handler'))
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setPublic(false)
        ;
        $container->register(SymfonyExceptionHandler::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setPublic(false)
        ;
    }

    private function configureTaskWorker(array $config, ContainerBuilder $container): array
    {
        if (!isset($config['settings']['worker_count'])) {
            return [];
        }

        $settings['task_worker_count'] = $config['settings']['worker_count'];
        $settings['task_use_object'] = true;
        $this->configureTaskWorkerServices($config['services'], $container);

        if ((bool) $container->getParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
            $settings['task_enable_coroutine'] = true;
        }

        return $settings;
    }

    private function configureTaskWorkerServices(array $config, ContainerBuilder $container): void
    {
        $loggingHandler = $container->findDefinition(ExceptionLoggingTransportHandler::class);
        $loggingHandler->setArgument(
            '$decorated',
            new Reference(ExceptionLoggingTransportHandler::class.'.inner')
        );
        $loggingHandler->setDecoratedService(TaskHandlerInterface::class, null, -9998);

        if (!$config['reset_handler']) {
            return;
        }

        $resetHandler = $container->findDefinition(ServiceResettingTransportHandler::class);
        $resetHandler->setArgument(
            '$decorated',
            new Reference(ServiceResettingTransportHandler::class.'.inner')
        );
        $resetHandler->setDecoratedService(TaskHandlerInterface::class, null, -9997);
    }

    private function assignSwooleConfiguration(
        array $swooleSettings,
        string $runningMode,
        ?int $maxConcurrency,
        ContainerBuilder $container
    ): void {
        $container->getDefinition(HttpServerConfiguration::class)
            ->addArgument(new Reference(Sockets::class))
            ->addArgument($runningMode)
            ->addArgument($swooleSettings)
            ->addArgument($maxConcurrency)
        ;
    }

    private function isProd(ContainerBuilder $container): bool
    {
        return 'prod' === $container->getParameter('kernel.environment');
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
