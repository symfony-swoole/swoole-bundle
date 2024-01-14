<?php

declare(strict_types=1);

use K911\Swoole\Bridge\Doctrine\ORM\EntityManagerStabilityChecker;
use K911\Swoole\Bridge\OpenSwoole\Metrics\MetricsProvider;
use K911\Swoole\Bridge\OpenSwoole\OpenSwooleFactory;
use K911\Swoole\Bridge\Swoole\SwooleFactory;
use K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\NonSharedSvcPoolConfigurator;
use K911\Swoole\Bridge\Symfony\Bundle\EventDispatcher\EventDispatchingServerStartHandler;
use K911\Swoole\Bridge\Symfony\Bundle\EventDispatcher\EventDispatchingWorkerErrorHandler;
use K911\Swoole\Bridge\Symfony\Bundle\EventDispatcher\EventDispatchingWorkerExitHandler;
use K911\Swoole\Bridge\Symfony\Bundle\EventDispatcher\EventDispatchingWorkerStartHandler;
use K911\Swoole\Bridge\Symfony\Bundle\EventDispatcher\EventDispatchingWorkerStopHandler;
use K911\Swoole\Bridge\Symfony\Container\CoWrapper;
use K911\Swoole\Bridge\Symfony\Container\Proxy\FileLocatorFactory;
use K911\Swoole\Bridge\Symfony\Container\Proxy\Generator;
use K911\Swoole\Bridge\Symfony\Container\Proxy\Instantiator;
use K911\Swoole\Bridge\Symfony\Container\Proxy\ProxyDirectoryHandler;
use K911\Swoole\Bridge\Symfony\Container\Proxy\UnmanagedFactoryInstantiator;
use K911\Swoole\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use K911\Swoole\Bridge\Symfony\HttpFoundation\NoOpStreamedResponseProcessor;
use K911\Swoole\Bridge\Symfony\HttpFoundation\RequestFactory;
use K911\Swoole\Bridge\Symfony\HttpFoundation\RequestFactoryInterface;
use K911\Swoole\Bridge\Symfony\HttpFoundation\ResponseHeadersAndStatusProcessor;
use K911\Swoole\Bridge\Symfony\HttpFoundation\ResponseProcessor;
use K911\Swoole\Bridge\Symfony\HttpFoundation\ResponseProcessorInjector;
use K911\Swoole\Bridge\Symfony\HttpFoundation\ResponseProcessorInjectorInterface;
use K911\Swoole\Bridge\Symfony\HttpFoundation\ResponseProcessorInterface;
use K911\Swoole\Bridge\Symfony\HttpFoundation\Session\SwooleSessionStorage;
use K911\Swoole\Bridge\Symfony\HttpFoundation\Session\SwooleSessionStorageFactory;
use K911\Swoole\Bridge\Symfony\HttpFoundation\SetRequestRuntimeConfiguration;
use K911\Swoole\Bridge\Symfony\HttpFoundation\StreamedResponseProcessor;
use K911\Swoole\Bridge\Symfony\HttpKernel\ContextReleasingHttpKernelRequestHandler;
use K911\Swoole\Bridge\Symfony\HttpKernel\CoroutineKernelPool;
use K911\Swoole\Bridge\Symfony\HttpKernel\HttpKernelRequestHandler;
use K911\Swoole\Bridge\Symfony\HttpKernel\KernelPoolInterface;
use K911\Swoole\Bridge\Symfony\HttpKernel\SimpleKernelPool;
use K911\Swoole\Bridge\Symfony\Messenger\ExceptionLoggingTransportHandler;
use K911\Swoole\Bridge\Symfony\Messenger\ServiceResettingTransportHandler;
use K911\Swoole\Common\Adapter\Swoole;
use K911\Swoole\Common\Adapter\SystemSwooleFactory;
use K911\Swoole\Common\System\System;
use K911\Swoole\Component\AtomicCounter;
use K911\Swoole\Component\ExceptionArrayTransformer;
use K911\Swoole\Component\GeneratedCollection;
use K911\Swoole\Component\Locking\Channel\ChannelMutexFactory;
use K911\Swoole\Component\Locking\FirstTimeOnly\FirstTimeOnlyMutexFactory;
use K911\Swoole\Metrics\SystemMetricsProviderRegistry;
use K911\Swoole\Server\Api\ApiServer;
use K911\Swoole\Server\Api\ApiServerClient;
use K911\Swoole\Server\Api\ApiServerClientFactory;
use K911\Swoole\Server\Api\ApiServerInterface;
use K911\Swoole\Server\Api\ApiServerRequestHandler;
use K911\Swoole\Server\Api\WithApiServerConfiguration;
use K911\Swoole\Server\Config\Sockets;
use K911\Swoole\Server\Configurator\CallableChainConfiguratorFactory;
use K911\Swoole\Server\Configurator\WithHttpServerConfiguration;
use K911\Swoole\Server\Configurator\WithRequestHandler;
use K911\Swoole\Server\Configurator\WithServerManagerStartHandler;
use K911\Swoole\Server\Configurator\WithServerManagerStopHandler;
use K911\Swoole\Server\Configurator\WithServerShutdownHandler;
use K911\Swoole\Server\Configurator\WithServerStartHandler;
use K911\Swoole\Server\Configurator\WithTaskFinishedHandler;
use K911\Swoole\Server\Configurator\WithTaskHandler;
use K911\Swoole\Server\Configurator\WithWorkerErrorHandler;
use K911\Swoole\Server\Configurator\WithWorkerExitHandler;
use K911\Swoole\Server\Configurator\WithWorkerStartHandler;
use K911\Swoole\Server\Configurator\WithWorkerStopHandler;
use K911\Swoole\Server\HttpServer;
use K911\Swoole\Server\HttpServerConfiguration;
use K911\Swoole\Server\LifecycleHandler\NoOpServerManagerStartHandler;
use K911\Swoole\Server\LifecycleHandler\NoOpServerManagerStopHandler;
use K911\Swoole\Server\LifecycleHandler\NoOpServerShutdownHandler;
use K911\Swoole\Server\LifecycleHandler\ServerManagerStartHandlerInterface;
use K911\Swoole\Server\LifecycleHandler\ServerManagerStopHandlerInterface;
use K911\Swoole\Server\LifecycleHandler\ServerShutdownHandlerInterface;
use K911\Swoole\Server\LifecycleHandler\ServerStartHandlerInterface;
use K911\Swoole\Server\Middleware\MiddlewareInjector;
use K911\Swoole\Server\RequestHandler\ExceptionHandler\ExceptionHandlerInterface;
use K911\Swoole\Server\RequestHandler\ExceptionHandler\JsonExceptionHandler;
use K911\Swoole\Server\RequestHandler\ExceptionHandler\ProductionExceptionHandler;
use K911\Swoole\Server\RequestHandler\ExceptionRequestHandler;
use K911\Swoole\Server\RequestHandler\LimitedRequestHandler;
use K911\Swoole\Server\RequestHandler\RequestHandlerInterface;
use K911\Swoole\Server\Runtime\BootableInterface;
use K911\Swoole\Server\Runtime\CallableBootManager;
use K911\Swoole\Server\Runtime\CallableBootManagerFactory;
use K911\Swoole\Server\Session\StorageInterface;
use K911\Swoole\Server\Session\SwooleTableStorage;
use K911\Swoole\Server\TaskHandler\NoOpTaskFinishedHandler;
use K911\Swoole\Server\TaskHandler\NoOpTaskHandler;
use K911\Swoole\Server\TaskHandler\TaskFinishedHandlerInterface;
use K911\Swoole\Server\TaskHandler\TaskHandlerInterface;
use K911\Swoole\Server\WorkerHandler\WorkerErrorHandlerInterface;
use K911\Swoole\Server\WorkerHandler\WorkerExitHandlerInterface;
use K911\Swoole\Server\WorkerHandler\WorkerStartHandlerInterface;
use K911\Swoole\Server\WorkerHandler\WorkerStopHandlerInterface;
use ProxyManager\Configuration;
use ProxyManager\Factory\AccessInterceptorValueHolderFactory;
use ProxyManager\FileLocator\FileLocator;
use ProxyManager\GeneratorStrategy\FileWriterGeneratorStrategy;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

use Symfony\Component\Filesystem\Filesystem;

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
        ])
    ;

    $services->set(System::class)
        ->factory([
            System::class,
            'create',
        ])
    ;

    $services->set(ExceptionArrayTransformer::class);

    $services->set(MiddlewareInjector::class);

    $services->set(ExceptionHandlerInterface::class);

    $services->set(ProductionExceptionHandler::class);

    $services->set(JsonExceptionHandler::class)
        ->arg('$exceptionArrayTransformer', service(ExceptionArrayTransformer::class))
        ->arg('$verbosity', null)
    ;

    $services->set(ExceptionRequestHandler::class)
        ->arg('$decorated', service(HttpKernelRequestHandler::class))
        ->arg('$exceptionHandler', service(ExceptionHandlerInterface::class))
    ;

    $services->set(SetRequestRuntimeConfiguration::class)
        ->tag('swoole_bundle.bootable_service', [
            'priority' => -1000,
        ])
    ;

    $services->alias(ResponseProcessorInjectorInterface::class, ResponseProcessorInjector::class);

    $services->set(ResponseProcessorInjector::class)
        ->arg('$responseProcessor', service('response_processor.headers_and_cookies.streamed'))
    ;

    $services->alias(KernelPoolInterface::class, SimpleKernelPool::class);

    $services->alias(RequestFactoryInterface::class, RequestFactory::class);

    $services->set(RequestFactory::class);

    $services->set(ResponseProcessorInterface::class, ResponseProcessor::class);

    $services->set(NoOpStreamedResponseProcessor::class)
        ->decorate(ResponseProcessorInterface::class, priority: -100)
        ->args([
            service('K911\Swoole\Bridge\Symfony\HttpFoundation\NoOpStreamedResponseProcessor.inner'),
        ])
    ;

    $services->set('response_processor.headers_and_cookies.default', ResponseHeadersAndStatusProcessor::class)
        ->decorate(ResponseProcessorInterface::class)
        ->args([
            service('response_processor.headers_and_cookies.default.inner'),
        ])
    ;

    $services->set(StreamedResponseProcessor::class);

    $services->set('response_processor.headers_and_cookies.streamed', ResponseHeadersAndStatusProcessor::class)
        ->decorate(StreamedResponseProcessor::class)
        ->args([
            service('response_processor.headers_and_cookies.streamed.inner'),
        ])
    ;

    $services->alias(RequestHandlerInterface::class, ExceptionRequestHandler::class);

    $services->set(SimpleKernelPool::class)
        ->arg('$kernel', service('kernel'))
    ;

    $services->set(HttpKernelRequestHandler::class)
        ->arg('$kernelPool', service(KernelPoolInterface::class))
        ->arg('$requestFactory', service(RequestFactoryInterface::class))
        ->arg('$processorInjector', service(ResponseProcessorInjectorInterface::class))
        ->arg('$responseProcessor', service(ResponseProcessorInterface::class))
        ->tag('swoole_bundle.bootable_service')
    ;

    $services->set(ContextReleasingHttpKernelRequestHandler::class)
        ->arg('$decorated', service(RequestHandlerInterface::class))
        ->arg('$coWrapper', service(CoWrapper::class))
    ;

    $services->set(SwooleSessionStorageFactory::class);

    $services->set(LimitedRequestHandler::class)
        ->arg('$decorated', service(RequestHandlerInterface::class))
        ->arg('$server', service(HttpServer::class))
        ->arg('$requestCounter', service(AtomicCounter::class))
        ->tag('swoole_bundle.bootable_service')
    ;

    $services->set(CallableBootManagerFactory::class);

    $services->set(SwooleTableStorage::class)
        ->factory([
            SwooleTableStorage::class,
            'fromDefaults',
        ])
    ;

    $services->alias(StorageInterface::class, SwooleTableStorage::class);

    $services->set(SwooleSessionStorage::class);

    $services->alias(BootableInterface::class, CallableBootManager::class);

    $services->set(CallableBootManager::class)
        ->factory([
            service(CallableBootManagerFactory::class),
            'make',
        ])
        ->args([
            tagged_iterator('swoole_bundle.bootable_service'),
        ])
    ;

    $services->set(HttpServer::class)
        ->arg('$configuration', service(HttpServerConfiguration::class))
    ;

    $services->alias(WorkerStartHandlerInterface::class, EventDispatchingWorkerStartHandler::class);

    $services->alias(WorkerStopHandlerInterface::class, EventDispatchingWorkerStopHandler::class);

    $services->alias(WorkerErrorHandlerInterface::class, EventDispatchingWorkerErrorHandler::class);

    $services->alias(WorkerExitHandlerInterface::class, EventDispatchingWorkerExitHandler::class);

    $services->alias(ServerStartHandlerInterface::class, EventDispatchingServerStartHandler::class);

    $services->set(EventDispatchingWorkerStartHandler::class)
        ->arg('$eventDispatcher', service('event_dispatcher'))
    ;

    $services->set(EventDispatchingWorkerStopHandler::class)
        ->arg('$eventDispatcher', service('event_dispatcher'))
    ;

    $services->set(EventDispatchingWorkerExitHandler::class)
        ->arg('$eventDispatcher', service('event_dispatcher'))
    ;

    $services->set(EventDispatchingWorkerErrorHandler::class)
        ->arg('$eventDispatcher', service('event_dispatcher'))
    ;

    $services->set(EventDispatchingServerStartHandler::class)
        ->arg('$eventDispatcher', service('event_dispatcher'))
    ;

    $services->set(ServerShutdownHandlerInterface::class, NoOpServerShutdownHandler::class);

    $services->set(ServerManagerStartHandlerInterface::class, NoOpServerManagerStartHandler::class);

    $services->set(ServerManagerStopHandlerInterface::class, NoOpServerManagerStopHandler::class);

    $services->set(TaskHandlerInterface::class, NoOpTaskHandler::class);

    $services->set(TaskFinishedHandlerInterface::class, NoOpTaskFinishedHandler::class);

    $services->set(ExceptionLoggingTransportHandler::class)
        ->arg('$decorated', null)
        ->arg('$logger', service('logger'))
        ->tag('monolog.logger', [
            'channel' => 'swoole',
        ])
    ;

    $services->set(ServiceResettingTransportHandler::class)
        ->arg('$decorated', null)
        ->arg('$resetter', service('services_resetter'))
    ;

    $services->set(ApiServerClientFactory::class)
        ->arg('$sockets', service(Sockets::class))
    ;

    $services->set(ApiServerClient::class)
        ->factory([
            service(ApiServerClientFactory::class),
            'newClient',
        ])
    ;

    $services->alias(ApiServerInterface::class, ApiServer::class);

    $services->set(ApiServer::class)
        ->arg('$server', service(HttpServer::class))
        ->arg('$serverConfiguration', service(HttpServerConfiguration::class))
    ;

    $services->set(Sockets::class);

    $services->set(HttpServerConfiguration::class);

    $services->set(WithHttpServerConfiguration::class)
        ->arg('$configuration', service(HttpServerConfiguration::class))
        ->tag('swoole_bundle.server_configurator')
    ;

    $services->set(WithServerShutdownHandler::class)
        ->arg('$handler', service(ServerShutdownHandlerInterface::class))
        ->tag('swoole_bundle.server_configurator')
    ;

    $services->set(WithServerManagerStartHandler::class)
        ->arg('$handler', service(ServerManagerStartHandlerInterface::class))
        ->tag('swoole_bundle.server_configurator')
    ;

    $services->set(WithServerManagerStopHandler::class)
        ->arg('$handler', service(ServerManagerStopHandlerInterface::class))
        ->tag('swoole_bundle.server_configurator')
    ;

    $services->set(WithWorkerStartHandler::class)
        ->arg('$handler', service(WorkerStartHandlerInterface::class))
        ->tag('swoole_bundle.server_configurator')
    ;

    $services->set(WithWorkerStopHandler::class)
        ->arg('$handler', service(WorkerStopHandlerInterface::class))
        ->tag('swoole_bundle.server_configurator')
    ;

    $services->set(WithWorkerErrorHandler::class)
        ->arg('$handler', service(WorkerErrorHandlerInterface::class))
        ->tag('swoole_bundle.server_configurator')
    ;

    $services->set(WithWorkerExitHandler::class)
        ->arg('$handler', service(WorkerExitHandlerInterface::class))
        ->tag('swoole_bundle.server_configurator')
    ;

    $services->set(WithTaskHandler::class)
        ->arg('$handler', service(TaskHandlerInterface::class))
        ->arg('$configuration', service(HttpServerConfiguration::class))
        ->tag('swoole_bundle.server_configurator')
    ;

    $services->set(WithTaskFinishedHandler::class)
        ->arg('$handler', service(TaskFinishedHandlerInterface::class))
        ->arg('$configuration', service(HttpServerConfiguration::class))
        ->tag('swoole_bundle.server_configurator')
    ;

    $services->set(CallableChainConfiguratorFactory::class);

    $services->set(WithApiServerConfiguration::class)
        ->arg('$sockets', service(Sockets::class))
        ->arg('$requestHandler', service('swoole_bundle.server.api_server.request_handler'))
        ->tag('swoole_bundle.server_configurator')
    ;

    $services->set(ApiServerRequestHandler::class)
        ->arg('$apiServer', service(ApiServerInterface::class))
    ;

    $services->set('swoole_bundle.server.api_server.request_handler', ExceptionRequestHandler::class)
        ->arg('$decorated', service(ApiServerRequestHandler::class))
        ->arg('$exceptionHandler', service(ExceptionHandlerInterface::class))
    ;

    $services->set('swoole_bundle.server.http_server.configurator_collection', GeneratedCollection::class)
        ->arg('$itemCollection', tagged_iterator('swoole_bundle.server_configurator'))
        ->arg('$items', [])
    ;

    $services->alias('swoole_bundle.session.table_storage', SwooleSessionStorage::class);

    $services->alias('swoole_bundle.session.table_storage_factory', SwooleSessionStorageFactory::class);

    $services->set('swoole_bundle.server.http_server.configurator.with_request_handler', WithRequestHandler::class)
        ->arg('$requestHandler', service(RequestHandlerInterface::class))
    ;

    $services->set('swoole_bundle.server.http_server.configurator.with_limited_request_handler', WithRequestHandler::class)
        ->arg('$requestHandler', service(LimitedRequestHandler::class))
    ;

    $services->set('swoole_bundle.server.http_server.configurator.default_handler', WithServerStartHandler::class)
        ->arg('$handler', service(EventDispatchingServerStartHandler::class))
        ->arg('$configuration', service(HttpServerConfiguration::class))
        ->tag('swoole_bundle.server_configurator')
    ;

    $services->set(ProxyDirectoryHandler::class)
        ->arg('$fileSystem', service('swoole_bundle.filesystem'))
        ->arg('$proxyDir', '%swoole_bundle.service_proxy_cache_dir%')
    ;

    $services->set(FileLocatorFactory::class)
        ->arg('$directoryHandler', service(ProxyDirectoryHandler::class))
    ;

    $services->set('swoole_bundle.service_pool.locking', ChannelMutexFactory::class);

    $services->set('swoole_bundle.unmanaged_factory_first_time.locking', FirstTimeOnlyMutexFactory::class)
        ->arg('$wrapped', service('swoole_bundle.service_pool.locking'))
    ;

    $services->set(Instantiator::class)
        ->arg('$proxyGenerator', service(Generator::class))
    ;

    $services->set(UnmanagedFactoryInstantiator::class)
        ->arg('$proxyFactory', service('swoole_bundle.unmanaged_factory_proxy_factory'))
        ->arg('$instantiator', service(Instantiator::class))
        ->arg('$servicePoolContainer', service(ServicePoolContainer::class))
        ->arg('$limitLocking', service('swoole_bundle.service_pool.locking'))
        ->arg('$newInstanceLocking', service('swoole_bundle.unmanaged_factory_first_time.locking'))
    ;

    $services->set('swoole_bundle.filesystem', Filesystem::class);

    $services->set(Generator::class)
        ->arg('$configuration', service('swoole_bundle.service_proxy_configuration'))
    ;

    $services->set('swoole_bundle.unmanaged_factory_proxy_factory', AccessInterceptorValueHolderFactory::class)
        ->arg('$configuration', service('swoole_bundle.service_proxy_configuration'))
    ;

    $services->set('swoole_bundle.service_proxy_configuration', Configuration::class)
        ->call('setGeneratorStrategy', [
            service('swoole_bundle.repository_proxy_file_writer_generator'),
        ])
        ->call('setProxiesTargetDir', [
            '%swoole_bundle.service_proxy_cache_dir%',
        ])
    ;

    $services->set('swoole_bundle.repository_proxy_file_writer_generator', FileWriterGeneratorStrategy::class)
        ->arg('$fileLocator', service('swoole_bundle.repository_proxy_file_locator'))
    ;

    $services->set('swoole_bundle.repository_proxy_file_locator', FileLocator::class)
        ->factory([
            service(FileLocatorFactory::class),
            'createFileLocator',
        ])
        ->arg('$proxiesDirectory', '%swoole_bundle.service_proxy_cache_dir%')
    ;

    $services->set(CoWrapper::class)
        ->arg('$servicePoolContainer', service(ServicePoolContainer::class))
    ;

    $services->set(ServicePoolContainer::class)
        ->arg('$pools', [
        ])
    ;

    $services->set(EntityManagerStabilityChecker::class)
        ->tag('swoole_bundle.stability_checker')
    ;

    $services->set(CoroutineKernelPool::class)
        ->arg('$kernel', service('kernel'))
    ;

    $services->set(NonSharedSvcPoolConfigurator::class)
        ->arg('$container', service(ServicePoolContainer::class))
    ;

    $services->set(MetricsProvider::class)
        ->tag('swoole_bundle.metrics_provider', [
            'extension' => K911\Swoole\Common\System\Extension::OPENSWOOLE,
        ])
    ;

    $services->set(K911\Swoole\Bridge\Swoole\Metrics\MetricsProvider::class)
        ->tag('swoole_bundle.metrics_provider', [
            'extension' => K911\Swoole\Common\System\Extension::SWOOLE,
        ])
    ;

    $services->set(SystemMetricsProviderRegistry::class)
        ->arg('$system', service(System::class))
        ->arg('$metricsProviders', tagged_iterator(tag: 'swoole_bundle.metrics_provider', indexAttribute: 'extension'))
    ;

    $services->set(K911\Swoole\Metrics\MetricsProvider::class)
        ->factory([
            service(SystemMetricsProviderRegistry::class),
            'get',
        ])
    ;

    $services->set(OpenSwooleFactory::class)
        ->tag('swoole_bundle.swoole_adapter_factory', [
            'extension' => K911\Swoole\Common\System\Extension::OPENSWOOLE,
        ])
    ;

    $services->set(SwooleFactory::class)
        ->tag('swoole_bundle.swoole_adapter_factory', [
            'extension' => K911\Swoole\Common\System\Extension::SWOOLE,
        ])
    ;

    $services->set(SystemSwooleFactory::class)
        ->arg('$system', service(System::class))
        ->arg('$adapterFactories', tagged_iterator(tag: 'swoole_bundle.swoole_adapter_factory', indexAttribute: 'extension'))
    ;

    $services->set(Swoole::class)
        ->factory([
            service(SystemSwooleFactory::class),
            'newInstance',
        ])
    ;
};
