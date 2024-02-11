<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle;

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Driver;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Report\PHP;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\ServerManagerStartHandler;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\ServerManagerStopHandler;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\ServerShutdownHandler;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\ServerStartHandler;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandler;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandler;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerStartHandler;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\EventListeners\{
    CoverageFinishOnConsoleTerminate,
    CoverageStartOnConsoleCommandEventListener,
};
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\RequestHandler\CodeCoverageRequestHandler;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\ServerLifecycle\CoverageFinishOnServerShutdown;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\ServerLifecycle\CoverageStartOnServerManagerStart;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\ServerLifecycle\CoverageStartOnServerManagerStop;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\ServerLifecycle\CoverageStartOnServerStart;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\ServerLifecycle\CoverageStartOnServerWorkerStart;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\TaskHandler\CodeCoverageTaskHandler;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class CoverageBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->autowire(PHP::class)
            ->setShared(false);
        $container->register(Filter::class);
        $container->register(Selector::class);
        $container->register(Driver::class)
            ->setShared(false)
            ->setFactory([new Reference(Selector::class), 'forLineCoverage'])
            ->setArgument('$filter', new Reference(Filter::class));
        $container->register(CodeCoverage::class)
            ->setShared(false)
            ->setArguments([
                '$driver' => new Reference(Driver::class),
                '$filter' => new Reference(Filter::class),
            ]);
        $container->autowire(CodeCoverageManager::class)
            ->setShared(false)
            ->addTag('kernel.reset', ['method' => 'reset']);

        $this->registerSingleProcessCoverageFlow($container);
        $this->registerServerCoverageFlow($container);
    }

    private function registerSingleProcessCoverageFlow(ContainerBuilder $container): void
    {
        $container->register(CoverageStartOnConsoleCommandEventListener::class)
            ->setPublic(false)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->addTag('kernel.event_listener', ['event' => 'console.command']);

        $container->register(CoverageFinishOnConsoleTerminate::class)
            ->setPublic(false)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->addTag('kernel.event_listener', ['event' => 'console.terminate']);
    }

    private function registerServerCoverageFlow(ContainerBuilder $container): void
    {
        $container->autowire(CodeCoverageRequestHandler::class)
            ->setPublic(false)
            ->setAutoconfigured(true)
            ->setArgument('$decorated', new Reference(CodeCoverageRequestHandler::class . '.inner'))
            ->setDecoratedService(RequestHandler::class, null, -9999);

        $container->autowire(
            'swoole_bundle.server.api_server.coverage_request_handler',
            CodeCoverageRequestHandler::class
        )
            ->setPublic(false)
            ->setAutoconfigured(true)
            ->setArgument('$decorated', new Reference('swoole_bundle.server.api_server.coverage_request_handler.inner'))
            ->setDecoratedService('swoole_bundle.server.api_server.request_handler', null, -9999);

        $container->autowire(CoverageStartOnServerStart::class)
            ->setPublic(false)
            ->setAutoconfigured(true)
            ->setArgument('$decorated', new Reference(CoverageStartOnServerStart::class . '.inner'))
            ->setDecoratedService(ServerStartHandler::class);

        $container->autowire(CoverageFinishOnServerShutdown::class)
            ->setPublic(false)
            ->setAutoconfigured(true)
            ->setArgument('$decorated', new Reference(CoverageFinishOnServerShutdown::class . '.inner'))
            ->setDecoratedService(ServerShutdownHandler::class);

        $container->autowire(CoverageStartOnServerWorkerStart::class)
            ->setPublic(false)
            ->setAutoconfigured(true)
            ->setArgument('$decorated', new Reference(CoverageStartOnServerWorkerStart::class . '.inner'))
            ->setDecoratedService(WorkerStartHandler::class);

        $container->autowire(CoverageStartOnServerManagerStart::class)
            ->setPublic(false)
            ->setAutoconfigured(true)
            ->setArgument('$decorated', new Reference(CoverageStartOnServerManagerStart::class . '.inner'))
            ->setDecoratedService(ServerManagerStartHandler::class);

        $container->autowire(CoverageStartOnServerManagerStop::class)
            ->setPublic(false)
            ->setAutoconfigured(true)
            ->setArgument('$decorated', new Reference(CoverageStartOnServerManagerStop::class . '.inner'))
            ->setDecoratedService(ServerManagerStopHandler::class);

        $container->autowire(CodeCoverageTaskHandler::class)
            ->setPublic(false)
            ->setAutoconfigured(true)
            ->setArgument('$decorated', new Reference(CodeCoverageTaskHandler::class . '.inner'))
            ->setDecoratedService(TaskHandler::class);
    }
}
