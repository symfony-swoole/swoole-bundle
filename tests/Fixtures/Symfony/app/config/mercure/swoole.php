<?php

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Mercure\NullHub;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\MercureTraceableHubReportCommand;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Mercure\Debug\TraceableHub;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    /**
     * Coroutines on, which is the whole precondition: MercureProcessor runs from StatefulServicesPass, and
     * that pass returns immediately where coroutines are off.
     *
     * An environment of its own, like the mailer one, so that no other server in the suite compiles a hub it
     * has no use for.
     *
     * @see \SwooleBundle\SwooleBundle\Tests\Feature\MercureTraceableHubPoolingTest
     */
    $containerConfigurator->extension('swoole', [
        'http_server' => [
            // Required rather than decorative: with coroutines on, SwooleBundle::boot() asks for
            // swoole_bundle.error_handler.symfony_error_handler, which only the symfony handler registers.
            'exception_handler' => [
                'type' => 'symfony',
            ],
            'settings' => [
                'worker_count' => 1,
            ],
        ],
        'platform' => [
            'coroutines' => [
                'enabled' => true,
                'max_concurrency' => 10,
                'max_service_instances' => 10,
            ],
        ],
    ]);

    $services = $containerConfigurator->services();

    /**
     * What MercureBundle builds with its profiler integration on, without MercureBundle: a hub, decorated by
     * a TraceableHub timing publishes on `debug.stopwatch` - the same class, the same arguments and the same
     * decoration, under the ids the bundle uses.
     *
     * Neither is autoconfigured, and that matters. MercureBundle registers these with `register()`, so they
     * carry no tags. Autoconfigured, the ResetInterface TraceableHub implements would give it a `kernel.reset`
     * tag of its own, and a test passing because of that tag would say nothing about MercureProcessor.
     */
    $services->set('mercure.hub.default', NullHub::class)
        ->autowire(false)
        ->autoconfigure(false);

    $services->set('mercure.hub.default.traceable', TraceableHub::class)
        ->autowire(false)
        ->autoconfigure(false)
        ->decorate('mercure.hub.default')
        ->args([service('.inner'), service('debug.stopwatch')]);

    // By id rather than autowired: HubInterface is private here, and the id is what the decoration resolves.
    $services->set(MercureTraceableHubReportCommand::class)
        ->autowire(false)
        ->autoconfigure(true)
        ->arg('$hub', service('mercure.hub.default'));
};
