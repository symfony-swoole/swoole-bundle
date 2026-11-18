<?php

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\NoAutowiring\PerFirewallResettable;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('env(WORKER_COUNT)', 1);

    $parameters->set('env(TASK_WORKER_COUNT)', 1);

    $parameters->set('env(REACTOR_COUNT)', 1);

    $containerConfigurator->extension('swoole', [
        'http_server' => [
            'exception_handler' => [
                'type' => 'symfony',
            ],
        ],
        'task_worker' => [
            'settings' => [
                'worker_count' => '%env(int:TASK_WORKER_COUNT)%',
            ],
        ],
        'platform' => [
            'coroutines' => [
                'enabled' => true,
                'max_concurrency' => 30,
                'max_service_instances' => 20,
            ],
        ],
    ]);

    $services = $containerConfigurator->services();

    // Templates the way bundles register them for their per-firewall services to be copied from, which can
    // never be instantiated and which the stateful services compiler pass therefore has to leave alone.
    //
    // The first one is what scheb/2fa-bundle does: not marked abstract, tagged kernel.reset, and holding an
    // abstract_arg() only a firewall can fill in.
    $services->set('test.template.abstract_argument', PerFirewallResettable::class)
        ->args([abstract_arg('Firewall name')])
        ->tag('kernel.reset', ['method' => 'reset']);

    // The second one is marked abstract. It cannot carry kernel.reset - Symfony's own ResettableServicePass
    // refuses an abstract service with that tag - so it is tagged as a stateful service instead.
    $services->set('test.template.abstract_definition', PerFirewallResettable::class)
        ->abstract()
        ->args(['template'])
        ->tag(ContainerConstants::TAG_STATEFUL_SERVICE);

    // What each template is there for: a child with the firewall filled in, tagged the way a real one has to
    // be, since a child does not inherit its parent's tags. These are instantiable and stay pooled.
    //
    // `index_0` is what ChildDefinition::replaceArgument(0) writes, and the only way to say it here: a plain
    // `0` would be appended to the parent's arguments and leave the abstract one in place.
    $services->set('test.template.abstract_argument.main')
        ->parent('test.template.abstract_argument')
        ->arg('index_0', 'main')
        ->tag('kernel.reset', ['method' => 'reset'])
        ->public();

    $services->set('test.template.abstract_definition.main')
        ->parent('test.template.abstract_definition')
        ->arg('index_0', 'main')
        ->tag(ContainerConstants::TAG_STATEFUL_SERVICE)
        ->public();
};
