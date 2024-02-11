<?php

declare(strict_types=1);

use ProxyManager\Configuration;
use ProxyManager\Factory\AccessInterceptorValueHolderFactory;
use ProxyManager\FileLocator\FileLocator;
use ProxyManager\GeneratorStrategy\FileWriterGeneratorStrategy;
use SwooleBundle\SwooleBundle\Bridge\Doctrine\ORM\EntityManagerStabilityChecker;
use SwooleBundle\SwooleBundle\Bridge\OpenSwoole\Metrics\MetricsProvider as OpenSwooleMetricsProvider;
use SwooleBundle\SwooleBundle\Bridge\OpenSwoole\OpenSwooleFactory;
use SwooleBundle\SwooleBundle\Bridge\Swoole\Metrics\MetricsProvider as SwooleMetricsProvider;
use SwooleBundle\SwooleBundle\Bridge\Swoole\SwooleFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\{
    NonSharedSvcPoolConfigurator,
};
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\EventDispatcher\EventDispatchingServerStartHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\EventDispatcher\EventDispatchingWorkerErrorHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\EventDispatcher\EventDispatchingWorkerExitHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\EventDispatcher\EventDispatchingWorkerStartHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\EventDispatcher\EventDispatchingWorkerStopHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\CoWrapper;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\FileLocatorFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generator;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Instantiator;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ProxyDirectoryHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\UnmanagedFactoryInstantiator;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\DefaultRequestFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\DefaultResponseProcessorInjector;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\EndResponseProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\NoOpStreamedResponseProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\RequestFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\ResponseHeadersAndStatusProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\ResponseProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\ResponseProcessorInjector;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\SwooleSessionStorage;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\SwooleSessionStorageFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\SetRequestRuntimeConfiguration;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\StreamedResponseProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel\ContextReleasingHttpKernelRequestHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel\CoroutineKernelPool;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel\HttpKernelRequestHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel\KernelPool;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel\SimpleKernelPool;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\ExceptionLoggingTransportHandler;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\ServiceResettingTransportHandler;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Common\Adapter\SystemSwooleFactory;
use SwooleBundle\SwooleBundle\Common\System\Extension;
use SwooleBundle\SwooleBundle\Common\System\System;
use SwooleBundle\SwooleBundle\Component\AtomicCounter;
use SwooleBundle\SwooleBundle\Component\ExceptionArrayTransformer;
use SwooleBundle\SwooleBundle\Component\GeneratedCollection;
use SwooleBundle\SwooleBundle\Component\Locking\Channel\ChannelMutexFactory;
use SwooleBundle\SwooleBundle\Component\Locking\FirstTimeOnly\FirstTimeOnlyMutexFactory;
use SwooleBundle\SwooleBundle\Metrics\MetricsProvider;
use SwooleBundle\SwooleBundle\Metrics\SystemMetricsProviderRegistry;
use SwooleBundle\SwooleBundle\Server\Api\Api;
use SwooleBundle\SwooleBundle\Server\Api\ApiServer;
use SwooleBundle\SwooleBundle\Server\Api\ApiServerClient;
use SwooleBundle\SwooleBundle\Server\Api\ApiServerClientFactory;
use SwooleBundle\SwooleBundle\Server\Api\ApiServerRequestHandler;
use SwooleBundle\SwooleBundle\Server\Api\WithApiServerConfiguration;
use SwooleBundle\SwooleBundle\Server\Config\Sockets;
use SwooleBundle\SwooleBundle\Server\Configurator\CallableChainConfiguratorFactory;
use SwooleBundle\SwooleBundle\Server\Configurator\WithHttpServerConfiguration;
use SwooleBundle\SwooleBundle\Server\Configurator\WithRequestHandler;
use SwooleBundle\SwooleBundle\Server\Configurator\WithServerManagerStartHandler;
use SwooleBundle\SwooleBundle\Server\Configurator\WithServerManagerStopHandler;
use SwooleBundle\SwooleBundle\Server\Configurator\WithServerShutdownHandler;
use SwooleBundle\SwooleBundle\Server\Configurator\WithServerStartHandler;
use SwooleBundle\SwooleBundle\Server\Configurator\WithTaskFinishedHandler;
use SwooleBundle\SwooleBundle\Server\Configurator\WithTaskHandler;
use SwooleBundle\SwooleBundle\Server\Configurator\WithWorkerErrorHandler;
use SwooleBundle\SwooleBundle\Server\Configurator\WithWorkerExitHandler;
use SwooleBundle\SwooleBundle\Server\Configurator\WithWorkerStartHandler;
use SwooleBundle\SwooleBundle\Server\Configurator\WithWorkerStopHandler;
use SwooleBundle\SwooleBundle\Server\DefaultHttpServerConfiguration;
use SwooleBundle\SwooleBundle\Server\HttpServer;
use SwooleBundle\SwooleBundle\Server\HttpServerConfiguration;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\NoOpServerManagerStartHandler;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\NoOpServerManagerStopHandler;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\NoOpServerShutdownHandler;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\ServerManagerStartHandler;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\ServerManagerStopHandler;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\ServerShutdownHandler;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\ServerStartHandler;
use SwooleBundle\SwooleBundle\Server\Middleware\MiddlewareInjector;
use SwooleBundle\SwooleBundle\Server\RequestHandler\ExceptionHandler\ExceptionHandler;
use SwooleBundle\SwooleBundle\Server\RequestHandler\ExceptionHandler\JsonExceptionHandler;
use SwooleBundle\SwooleBundle\Server\RequestHandler\ExceptionHandler\ProductionExceptionHandler;
use SwooleBundle\SwooleBundle\Server\RequestHandler\ExceptionRequestHandler;
use SwooleBundle\SwooleBundle\Server\RequestHandler\LimitedRequestHandler;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandler;
use SwooleBundle\SwooleBundle\Server\Runtime\Bootable;
use SwooleBundle\SwooleBundle\Server\Runtime\CallableBootManager;
use SwooleBundle\SwooleBundle\Server\Runtime\CallableBootManagerFactory;
use SwooleBundle\SwooleBundle\Server\Session\Storage;
use SwooleBundle\SwooleBundle\Server\Session\SwooleTableStorage;
use SwooleBundle\SwooleBundle\Server\TaskHandler\NoOpTaskFinishedHandler;
use SwooleBundle\SwooleBundle\Server\TaskHandler\NoOpTaskHandler;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskFinishedHandler;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandler;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerErrorHandler;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerExitHandler;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerStartHandler;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerStopHandler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('swoole_bundle.coroutines_support.enabled', false);

    $parameters->set('swoole_bundle.coroutines_support.compile_processors', []);

    $parameters->set('swoole_bundle.coroutines_support.stability_checkers', []);

    $parameters->set('swoole_bundle.coroutines_support.max_service_instances', 100000);

    $parameters->set('swoole_bundle.cache_dir_name', 'swoole_bundle');

    $parameters->set('swoole_bundle.cache_dir', '%kernel.cache_dir%/%swoole_bundle.cache_dir_name%');

    $parameters->set('swoole_bundle.service_proxy_cache_dir', '%swoole_bundle.cache_dir%/services');

    $services = $containerConfigurator->services();

    $services->defaults();

    $services->set(AtomicCounter::class)
        ->factory([
            AtomicCounter::class,
            'fromZero',
        ]);

    $services->set(System::class)
        ->factory([
            System::class,
            'create',
        ]);

    $services->set(ExceptionArrayTransformer::class);

    $services->set(MiddlewareInjector::class);

    $services->set(ExceptionHandler::class);

    $services->set(ProductionExceptionHandler::class);

    $services->set(JsonExceptionHandler::class)
        ->arg('$exceptionArrayTransformer', service(ExceptionArrayTransformer::class))
        ->arg('$verbosity', null);

    $services->set(ExceptionRequestHandler::class)
        ->arg('$decorated', service(HttpKernelRequestHandler::class))
        ->arg('$exceptionHandler', service(ExceptionHandler::class));

    $services->set(SetRequestRuntimeConfiguration::class)
        ->tag('swoole_bundle.bootable_service', [
            'priority' => -1000,
        ]);

    $services->alias(ResponseProcessorInjector::class, DefaultResponseProcessorInjector::class);

    $services->set(DefaultResponseProcessorInjector::class)
        ->arg('$responseProcessor', service('response_processor.headers_and_cookies.streamed'));

    $services->alias(KernelPool::class, SimpleKernelPool::class);

    $services->alias(RequestFactory::class, DefaultRequestFactory::class);

    $services->set(DefaultRequestFactory::class);

    $services->set(ResponseProcessor::class, EndResponseProcessor::class);

    $services->set(NoOpStreamedResponseProcessor::class)
        ->decorate(ResponseProcessor::class, priority: -100)
        ->args([
            service('SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\NoOpStreamedResponseProcessor.inner'),
        ]);

    $services->set('response_processor.headers_and_cookies.default', ResponseHeadersAndStatusProcessor::class)
        ->decorate(ResponseProcessor::class)
        ->args([
            service('response_processor.headers_and_cookies.default.inner'),
        ]);

    $services->set(StreamedResponseProcessor::class);

    $services->set('response_processor.headers_and_cookies.streamed', ResponseHeadersAndStatusProcessor::class)
        ->decorate(StreamedResponseProcessor::class)
        ->args([
            service('response_processor.headers_and_cookies.streamed.inner'),
        ]);

    $services->alias(RequestHandler::class, ExceptionRequestHandler::class);

    $services->set(SimpleKernelPool::class)
        ->arg('$kernel', service('kernel'));

    $services->set(HttpKernelRequestHandler::class)
        ->arg('$kernelPool', service(KernelPool::class))
        ->arg('$requestFactory', service(RequestFactory::class))
        ->arg('$processorInjector', service(ResponseProcessorInjector::class))
        ->arg('$responseProcessor', service(ResponseProcessor::class))
        ->tag('swoole_bundle.bootable_service');

    $services->set(ContextReleasingHttpKernelRequestHandler::class)
        ->arg('$decorated', service(RequestHandler::class))
        ->arg('$coWrapper', service(CoWrapper::class));

    $services->set(SwooleSessionStorageFactory::class);

    $services->set(LimitedRequestHandler::class)
        ->arg('$decorated', service(RequestHandler::class))
        ->arg('$server', service(HttpServer::class))
        ->arg('$requestCounter', service(AtomicCounter::class))
        ->tag('swoole_bundle.bootable_service');

    $services->set(CallableBootManagerFactory::class);

    $services->set(SwooleTableStorage::class)
        ->factory([
            SwooleTableStorage::class,
            'fromDefaults',
        ]);

    $services->alias(Storage::class, SwooleTableStorage::class);

    $services->set(SwooleSessionStorage::class);

    $services->alias(Bootable::class, CallableBootManager::class);

    $services->set(CallableBootManager::class)
        ->factory([
            service(CallableBootManagerFactory::class),
            'make',
        ])
        ->args([
            tagged_iterator('swoole_bundle.bootable_service'),
        ]);

    $services->set(HttpServer::class)
        ->arg('$configuration', service(HttpServerConfiguration::class));

    $services->alias(WorkerStartHandler::class, EventDispatchingWorkerStartHandler::class);

    $services->alias(WorkerStopHandler::class, EventDispatchingWorkerStopHandler::class);

    $services->alias(WorkerErrorHandler::class, EventDispatchingWorkerErrorHandler::class);

    $services->alias(WorkerExitHandler::class, EventDispatchingWorkerExitHandler::class);

    $services->alias(ServerStartHandler::class, EventDispatchingServerStartHandler::class);

    $services->set(EventDispatchingWorkerStartHandler::class)
        ->arg('$eventDispatcher', service('event_dispatcher'));

    $services->set(EventDispatchingWorkerStopHandler::class)
        ->arg('$eventDispatcher', service('event_dispatcher'));

    $services->set(EventDispatchingWorkerExitHandler::class)
        ->arg('$eventDispatcher', service('event_dispatcher'));

    $services->set(EventDispatchingWorkerErrorHandler::class)
        ->arg('$eventDispatcher', service('event_dispatcher'));

    $services->set(EventDispatchingServerStartHandler::class)
        ->arg('$eventDispatcher', service('event_dispatcher'));

    $services->set(ServerShutdownHandler::class, NoOpServerShutdownHandler::class);

    $services->set(ServerManagerStartHandler::class, NoOpServerManagerStartHandler::class);

    $services->set(ServerManagerStopHandler::class, NoOpServerManagerStopHandler::class);

    $services->set(TaskHandler::class, NoOpTaskHandler::class);

    $services->set(TaskFinishedHandler::class, NoOpTaskFinishedHandler::class);

    $services->set(ExceptionLoggingTransportHandler::class)
        ->arg('$decorated', null)
        ->arg('$logger', service('logger'))
        ->tag('monolog.logger', [
            'channel' => 'swoole',
        ]);

    $services->set(ServiceResettingTransportHandler::class)
        ->arg('$decorated', null)
        ->arg('$resetter', service('services_resetter'));

    $services->set(ApiServerClientFactory::class)
        ->arg('$sockets', service(Sockets::class));

    $services->set(ApiServerClient::class)
        ->factory([
            service(ApiServerClientFactory::class),
            'newClient',
        ]);

    $services->alias(Api::class, ApiServer::class);

    $services->set(ApiServer::class)
        ->arg('$server', service(HttpServer::class))
        ->arg('$serverConfiguration', service(HttpServerConfiguration::class));

    $services->set(Sockets::class);

    $services->set(HttpServerConfiguration::class, DefaultHttpServerConfiguration::class);

    $services->set(WithHttpServerConfiguration::class)
        ->arg('$configuration', service(HttpServerConfiguration::class))
        ->tag('swoole_bundle.server_configurator');

    $services->set(WithServerShutdownHandler::class)
        ->arg('$handler', service(ServerShutdownHandler::class))
        ->tag('swoole_bundle.server_configurator');

    $services->set(WithServerManagerStartHandler::class)
        ->arg('$handler', service(ServerManagerStartHandler::class))
        ->tag('swoole_bundle.server_configurator');

    $services->set(WithServerManagerStopHandler::class)
        ->arg('$handler', service(ServerManagerStopHandler::class))
        ->tag('swoole_bundle.server_configurator');

    $services->set(WithWorkerStartHandler::class)
        ->arg('$handler', service(WorkerStartHandler::class))
        ->tag('swoole_bundle.server_configurator');

    $services->set(WithWorkerStopHandler::class)
        ->arg('$handler', service(WorkerStopHandler::class))
        ->tag('swoole_bundle.server_configurator');

    $services->set(WithWorkerErrorHandler::class)
        ->arg('$handler', service(WorkerErrorHandler::class))
        ->tag('swoole_bundle.server_configurator');

    $services->set(WithWorkerExitHandler::class)
        ->arg('$handler', service(WorkerExitHandler::class))
        ->tag('swoole_bundle.server_configurator');

    $services->set(WithTaskHandler::class)
        ->arg('$handler', service(TaskHandler::class))
        ->arg('$configuration', service(HttpServerConfiguration::class))
        ->tag('swoole_bundle.server_configurator');

    $services->set(WithTaskFinishedHandler::class)
        ->arg('$handler', service(TaskFinishedHandler::class))
        ->arg('$configuration', service(HttpServerConfiguration::class))
        ->tag('swoole_bundle.server_configurator');

    $services->set(CallableChainConfiguratorFactory::class);

    $services->set(WithApiServerConfiguration::class)
        ->arg('$sockets', service(Sockets::class))
        ->arg('$requestHandler', service('swoole_bundle.server.api_server.request_handler'))
        ->tag('swoole_bundle.server_configurator');

    $services->set(ApiServerRequestHandler::class)
        ->arg('$apiServer', service(Api::class));

    $services->set('swoole_bundle.server.api_server.request_handler', ExceptionRequestHandler::class)
        ->arg('$decorated', service(ApiServerRequestHandler::class))
        ->arg('$exceptionHandler', service(ExceptionHandler::class));

    $services->set('swoole_bundle.server.http_server.configurator_collection', GeneratedCollection::class)
        ->arg('$itemCollection', tagged_iterator('swoole_bundle.server_configurator'))
        ->arg('$items', []);

    $services->alias('swoole_bundle.session.table_storage', SwooleSessionStorage::class);

    $services->alias('swoole_bundle.session.table_storage_factory', SwooleSessionStorageFactory::class);

    $services->set('swoole_bundle.server.http_server.configurator.with_request_handler', WithRequestHandler::class)
        ->arg('$requestHandler', service(RequestHandler::class));

    $services->set(
        'swoole_bundle.server.http_server.configurator.with_limited_request_handler',
        WithRequestHandler::class
    )
        ->arg('$requestHandler', service(LimitedRequestHandler::class));

    $services->set('swoole_bundle.server.http_server.configurator.default_handler', WithServerStartHandler::class)
        ->arg('$handler', service(EventDispatchingServerStartHandler::class))
        ->arg('$configuration', service(HttpServerConfiguration::class))
        ->tag('swoole_bundle.server_configurator');

    $services->set(ProxyDirectoryHandler::class)
        ->arg('$fileSystem', service('swoole_bundle.filesystem'))
        ->arg('$proxyDir', '%swoole_bundle.service_proxy_cache_dir%');

    $services->set(FileLocatorFactory::class)
        ->arg('$directoryHandler', service(ProxyDirectoryHandler::class));

    $services->set('swoole_bundle.service_pool.locking', ChannelMutexFactory::class);

    $services->set('swoole_bundle.unmanaged_factory_first_time.locking', FirstTimeOnlyMutexFactory::class)
        ->arg('$wrapped', service('swoole_bundle.service_pool.locking'));

    $services->set(Instantiator::class)
        ->arg('$proxyGenerator', service(Generator::class));

    $services->set(UnmanagedFactoryInstantiator::class)
        ->arg('$proxyFactory', service('swoole_bundle.unmanaged_factory_proxy_factory'))
        ->arg('$instantiator', service(Instantiator::class))
        ->arg('$servicePoolContainer', service(ServicePoolContainer::class))
        ->arg('$limitLocking', service('swoole_bundle.service_pool.locking'))
        ->arg('$newInstanceLocking', service('swoole_bundle.unmanaged_factory_first_time.locking'));

    $services->set('swoole_bundle.filesystem', Filesystem::class);

    $services->set(Generator::class)
        ->arg('$configuration', service('swoole_bundle.service_proxy_configuration'));

    $services->set('swoole_bundle.unmanaged_factory_proxy_factory', AccessInterceptorValueHolderFactory::class)
        ->arg('$configuration', service('swoole_bundle.service_proxy_configuration'));

    $services->set('swoole_bundle.service_proxy_configuration', Configuration::class)
        ->call('setGeneratorStrategy', [
            service('swoole_bundle.repository_proxy_file_writer_generator'),
        ])
        ->call('setProxiesTargetDir', [
            '%swoole_bundle.service_proxy_cache_dir%',
        ]);

    $services->set('swoole_bundle.repository_proxy_file_writer_generator', FileWriterGeneratorStrategy::class)
        ->arg('$fileLocator', service('swoole_bundle.repository_proxy_file_locator'));

    $services->set('swoole_bundle.repository_proxy_file_locator', FileLocator::class)
        ->factory([
            service(FileLocatorFactory::class),
            'createFileLocator',
        ])
        ->arg('$proxiesDirectory', '%swoole_bundle.service_proxy_cache_dir%');

    $services->set(CoWrapper::class)
        ->arg('$servicePoolContainer', service(ServicePoolContainer::class));

    $services->set(ServicePoolContainer::class)
        ->arg('$pools', [
        ]);

    $services->set(EntityManagerStabilityChecker::class)
        ->tag('swoole_bundle.stability_checker');

    $services->set(CoroutineKernelPool::class)
        ->arg('$kernel', service('kernel'));

    $services->set(NonSharedSvcPoolConfigurator::class)
        ->arg('$container', service(ServicePoolContainer::class));

    $services->set(OpenSwooleMetricsProvider::class)
        ->tag('swoole_bundle.metrics_provider', [
            'extension' => Extension::OPENSWOOLE,
        ]);

    $services->set(SwooleMetricsProvider::class)
        ->tag('swoole_bundle.metrics_provider', [
            'extension' => Extension::SWOOLE,
        ]);

    $services->set(SystemMetricsProviderRegistry::class)
        ->arg('$system', service(System::class))
        ->arg('$metricsProviders', tagged_iterator(tag: 'swoole_bundle.metrics_provider', indexAttribute: 'extension'));

    $services->set(MetricsProvider::class)
        ->factory([
            service(SystemMetricsProviderRegistry::class),
            'get',
        ]);

    $services->set(OpenSwooleFactory::class)
        ->tag('swoole_bundle.swoole_adapter_factory', [
            'extension' => Extension::OPENSWOOLE,
        ]);

    $services->set(SwooleFactory::class)
        ->tag('swoole_bundle.swoole_adapter_factory', [
            'extension' => Extension::SWOOLE,
        ]);

    $services->set(SystemSwooleFactory::class)
        ->arg('$system', service(System::class))
        ->arg(
            '$adapterFactories',
            tagged_iterator(tag: 'swoole_bundle.swoole_adapter_factory', indexAttribute: 'extension')
        );

    $services->set(Swoole::class)
        ->factory([
            service(SystemSwooleFactory::class),
            'newInstance',
        ]);
};
