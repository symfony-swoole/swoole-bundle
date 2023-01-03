<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

final class FinalizeDefinitionsAfterRemovalPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
            return;
        }

        if (!$container->getParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
            return;
        }

        $this->makeResettableServicesActive($container);
    }

    /**
     * by default ins Symfony, all resettable services are ignored during reset if they haven't been instantiated yet.
     * when using coroutines, all resettable services need to be instantiated on first reset, because otherwise,
     * it would be possible for a coroutine to acquire them not resetted in the scenario described below.
     *
     * 1) coroutine 1 starts, service reset runs but the resettable service is not instantiated yet, so there is no reset
     * 2) coroutine 2 starts in the same manner
     * 3) coroutine 1 needs the service so it instantiates the service pool
     * 4) coroutine 1 uses the stateful service and returns it to the service pool (not resetted)
     * 5) coroutine 2 needs the service, there is already a service pool so it acquires the instance formerly used
     *    in coroutine 1 (which still is not resetted and coroutine 2 is already after the reset phase)
     * 6) coroutine 2 uses the not resetted service with state remembered from the other coroutine
     *
     * the instantiation on first reset is forced by using the RUNTIME_EXCEPTION_ON_INVALID_REFERENCE in service reference
     *
     * all this is only happening for resetters that are global and which have not been changed to service pool resetters
     */
    private function makeResettableServicesActive(ContainerBuilder $container): void
    {
        $resetterDef = $container->findDefinition('services_resetter');

        if ($resetterDef->hasTag('kernel.reset')) {
            return;
        }

        /** @var IteratorArgument $resetters */
        $resetters = $resetterDef->getArgument(0);
        $resetterValues = $resetters->getValues();
        $newReferences = [];

        foreach ($resetterValues as $key => $reference) {
            if (!$container->hasDefinition($key)) {
                continue;
            }

            $newReferences[$key] = new Reference((string) $reference, ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        }

        $resetters->setValues($newReferences);
    }
}
