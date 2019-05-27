<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use K911\Swoole\Bridge\Doctrine\ORM\EntityManagersHandler;
use K911\Swoole\Bridge\Symfony\Logging\ChannelFactory;
use K911\Swoole\Bridge\Symfony\Logging\MasterLogger;
use K911\Swoole\Bridge\Symfony\HttpFoundation\CloudFrontRequestFactory;
use K911\Swoole\Bridge\Symfony\HttpFoundation\RequestFactoryInterface;
use K911\Swoole\Bridge\Symfony\HttpFoundation\TrustAllProxiesRequestHandler;
use K911\Swoole\Bridge\Symfony\HttpKernel\DebugHttpKernelRequestHandler;
use K911\Swoole\Bridge\Symfony\RequestCycle\InitializerInterface;
use K911\Swoole\Bridge\Symfony\RequestCycle\TerminatorInterface;
use K911\Swoole\Server\Config\Socket;
use K911\Swoole\Server\Config\Sockets;
use K911\Swoole\Server\Configurator\ConfiguratorInterface;
use K911\Swoole\Server\HttpServerConfiguration;
use K911\Swoole\Server\RequestHandler\AdvancedStaticFilesServer;
use K911\Swoole\Server\RequestHandler\RequestHandlerInterface;
use K911\Swoole\Server\Runtime\BootableInterface;
use K911\Swoole\Server\Runtime\HMR\HotModuleReloaderInterface;
use K911\Swoole\Server\Runtime\HMR\InotifyHMR;
use K911\Swoole\Server\WorkerHandler\HMRWorkerStartHandler;
use K911\Swoole\Server\WorkerHandler\WorkerStartHandlerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class SwooleExtension extends ConfigurableExtension
{
    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function loadInternal(array $mergedConfig, ContainerBuilder $container)
    {
        $mode = $mergedConfig['mode'];
        $swooleBundleEnabled = $mode === 'strict' || extension_loaded('swoole');

        $container->setParameter('swoole_bundle.enabled', $swooleBundleEnabled);

        // this is a hack to fulfill Symfony container conditions to use every env variable used in the config
        $this->registerHttpServerParameters($mergedConfig['http_server'], $container);

        if (!$swooleBundleEnabled) {
            return;
        }

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
        $loader->load('commands.yaml');

        $container->registerForAutoconfiguration(BootableInterface::class)
            ->addTag('swoole_bundle.bootable_service');
        $container->registerForAutoconfiguration(ConfiguratorInterface::class)
            ->addTag('swoole_bundle.server_configurator');
        $container->registerForAutoconfiguration(InitializerInterface::class)
            ->addTag('swoole_bundle.app_initializer');
        $container->registerForAutoconfiguration(TerminatorInterface::class)
            ->addTag('swoole_bundle.app_terminator');

        $this->registerHttpServer($mergedConfig['http_server'], $container);
    }

    /**
     * @param array            $httpServerConfig
     * @param ContainerBuilder $container
     */
    private function registerHttpServerParameters(array $httpServerConfig, ContainerBuilder $container): void
    {
        $container->setParameter('swoole.http_server.host', $httpServerConfig['host']);
        $container->setParameter('swoole.http_server.port', $httpServerConfig['port']);
        $container->setParameter('swoole.http_server.trusted_proxies', $httpServerConfig['trusted_proxies']);
        $container->setParameter('swoole.http_server.trusted_hosts', $httpServerConfig['trusted_hosts']);
        $container->setParameter('swoole.http_server.api.host', $httpServerConfig['api']['host']);
        $container->setParameter('swoole.http_server.api.port', $httpServerConfig['api']['port']);
    }

    /**
     * @param array            $httpServerConfig
     * @param ContainerBuilder $container
     *
     * @throws ServiceNotFoundException
     */
    private function registerHttpServer(array $httpServerConfig, ContainerBuilder $container): void
    {
        $this->registerHttpServerServices($httpServerConfig, $container);
        $this->registerHttpServerConfiguration($httpServerConfig, $container);
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     */
    private function registerHttpServerConfiguration(array $config, ContainerBuilder $container): void
    {
        [
            'static' => $static,
            'api' => $api,
            'hmr' => $hmr,
            'host' => $host,
            'port' => $port,
            'running_mode' => $runningMode,
            'socket_type' => $socketType,
            'ssl_enabled' => $sslEnabled,
            'settings' => $settings,
        ] = $config;

        if ('auto' === $static['strategy']) {
            $static['strategy'] = $this->isDebugOrNotProd($container) ? 'advanced' : 'off';
        }

        if ('advanced' === $static['strategy']) {
            $container->register(AdvancedStaticFilesServer::class)
                ->setArgument('$decorated', new Reference(AdvancedStaticFilesServer::class.'.inner'))
                ->setArgument('$configuration', new Reference(HttpServerConfiguration::class))
                ->setPublic(false)
                ->setDecoratedService(RequestHandlerInterface::class, null, -60)
                ->addTag('swoole_bundle.bootable_service');
        }

        $settings['serve_static'] = $static['strategy'];
        $settings['public_dir'] = $static['public_dir'];

        if ('auto' === $settings['log_level']) {
            $settings['log_level'] = $this->isDebug($container) ? 'debug' : 'notice';
        }

        if ('auto' === $hmr) {
            $hmr = $this->resolveAutoHMR();
        }

        $sockets = $container->getDefinition(Sockets::class)
            ->addArgument(new Definition(Socket::class, [$host, $port, $socketType, $sslEnabled]));

        if ($api['enabled']) {
            $sockets->addArgument(new Definition(Socket::class, [$api['host'], $api['port']]));
        }

        $container->getDefinition(HttpServerConfiguration::class)
            ->replaceArgument('$sockets', new Reference(Sockets::class))
            ->replaceArgument('$runningMode', $runningMode)
            ->replaceArgument('$settings', $settings);

        $this->registerHttpServerHMR($hmr, $container);
    }

    /**
     * @param string           $hmr
     * @param ContainerBuilder $container
     */
    private function registerHttpServerHMR(string $hmr, ContainerBuilder $container): void
    {
        if ('off' === $hmr || !$this->isDebug($container)) {
            return;
        }

        if ('inotify' === $hmr) {
            $container->register(InotifyHMR::class)
                ->setPublic(false)
                ->addTag('swoole_bundle.bootable_service');

            $container->register(HotModuleReloaderInterface::class, InotifyHMR::class)
                ->setPublic(false);
        }

        $container->register(HMRWorkerStartHandler::class)
            ->setPublic(false)
            ->setArgument('$hmr', new Reference(InotifyHMR::class))
            ->setArgument('$interval', 2000)
            ->setArgument('$decorated', new Reference(HMRWorkerStartHandler::class.'.inner'))
            ->setDecoratedService(WorkerStartHandlerInterface::class);
    }

    /**
     * @return string
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
     * @param array            $httpServerConfig
     * @param ContainerBuilder $container
     */
    private function registerHttpServerServices(array $httpServerConfig, ContainerBuilder $container): void
    {
        $servicesConfig = $httpServerConfig['services'];

        // RequestFactoryInterface
        // -----------------------
        if ($servicesConfig['cloudfront_proto_header_handler']) {
            $container->register(CloudFrontRequestFactory::class)
                ->addArgument(new Reference(CloudFrontRequestFactory::class.'.inner'))
                ->setPublic(false)
                ->setDecoratedService(RequestFactoryInterface::class, null, -10);
        }

        // RequestHandlerInterface
        // -------------------------
        if ($servicesConfig['trust_all_proxies_handler']) {
            $container->register(TrustAllProxiesRequestHandler::class)
                ->addArgument(new Reference(TrustAllProxiesRequestHandler::class.'.inner'))
                ->setPublic(false)
                ->setDecoratedService(RequestHandlerInterface::class, null, -10)
                ->addTag('swoole_bundle.bootable_service');
        }

        if ($servicesConfig['debug_handler'] || (null === $servicesConfig['debug_handler'] && $this->isDebug($container))) {
            $container->register(DebugHttpKernelRequestHandler::class)
                ->setArgument('$decorated', new Reference(DebugHttpKernelRequestHandler::class.'.inner'))
                ->setArgument('$kernel', new Reference('kernel'))
                ->setArgument('$container', new Reference('service_container'))
                ->setPublic(false)
                ->setDecoratedService(RequestHandlerInterface::class, null, -50);
        }

        // InitializerInterface && TerminatorInterface
        if (interface_exists(EntityManagerInterface::class) && $this->isBundleLoaded($container, 'doctrine')) {
            $container->register(EntityManagersHandler::class)
                ->setArgument('$doctrineRegistry', new Reference('doctrine'))
                ->setPublic(false)
                ->addTag('swoole_bundle.app_initializer')
                ->addTag('swoole_bundle.app_terminator');
        }

        // Channel logger
        if ($servicesConfig['channel_logger']) {
            $container->setParameter('swoole_bundle.channel_logger', true);
            $masterLoggerDef = $container->findDefinition(MasterLogger::class);
            $masterLoggerDef->addTag('kernel.event_listener', [
                'event' => 'console.command',
                'method' => 'onInitialize',
                'priority' => 1000000,
            ]);
            $masterLoggerDef->addTag('kernel.event_listener', [
                'event' => 'console.terminate',
                'method' => 'onTerminate',
                'priority' => -1000000,
            ]);

            if (!isset($httpServerConfig['settings'])) {
                return;
            }

            $settings = $httpServerConfig['settings'];
            $workerCount = null;

            if (isset($settings['worker_count'])) {
                $workerCount = $settings['worker_count'];
            }

            $chFactoryDef = $container->findDefinition(ChannelFactory::class);
            $chFactoryDef->replaceArgument('$workerCount', $workerCount);
        }
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

    /**
     * @param ContainerBuilder $container
     * @param string           $bundleName
     *
     * @return bool
     */
    private function isBundleLoaded(ContainerBuilder $container, string $bundleName): bool
    {
        $bundles = $container->getParameter('kernel.bundles');

        $bundleNameOnly = str_replace('bundle', '', mb_strtolower($bundleName));
        $fullBundleName = ucfirst($bundleNameOnly).'Bundle';

        return isset($bundles[$fullBundleName]);
    }

    /**
     * @param ContainerBuilder $container
     *
     * @return bool
     */
    private function isDebug(ContainerBuilder $container): bool
    {
        return $container->getParameter('kernel.debug');
    }

    /**
     * @param ContainerBuilder $container
     *
     * @return bool
     */
    private function isDebugOrNotProd(ContainerBuilder $container): bool
    {
        return $this->isDebug($container) || 'prod' !== $container->getParameter('kernel.environment');
    }
}
