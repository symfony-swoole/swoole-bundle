<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use K911\Swoole\Bridge\Symfony\Container\Proxy\UnmanagedFactoryInstantiator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class UnmanagedFactoryProxifier
{
    private ContainerBuilder $container;

    private FinalClassesProcessor $finalProcessor;

    public function __construct(ContainerBuilder $container, FinalClassesProcessor $finalProcessor)
    {
        $this->container = $container;
        $this->finalProcessor = $finalProcessor;
    }

    /**
     * returns new service id of the proxified service.
     */
    public function proxifyService(string $serviceId): string
    {
        if (!$this->container->has($serviceId)) {
            throw new \RuntimeException(sprintf('Service missing: %s', $serviceId));
        }

        $serviceDef = $this->prepareProxifiedService($serviceId);
        $wrappedServiceId = sprintf('%s.swoole_coop.wrapped_factory', $serviceId);
        $proxyDef = $this->prepareProxy($wrappedServiceId, $serviceDef);
        $serviceDef->clearTags();

        $this->container->setDefinition($serviceId, $proxyDef); // proxy swap
        $this->container->setDefinition($wrappedServiceId, $serviceDef); // old service for wrapping

        return $wrappedServiceId;
    }

    private function prepareProxifiedService(string $serviceId): Definition
    {
        $serviceDef = $this->container->findDefinition($serviceId);
        $this->finalProcessor->process($serviceDef->getClass());

        return $serviceDef;
    }

    private function prepareProxy(string $wrappedServiceId, Definition $serviceDef): Definition
    {
        /** @var class-string $serviceClass */
        $serviceClass = $serviceDef->getClass();
        $proxyDef = new Definition($serviceClass);
        $proxyDef->setPublic($serviceDef->isPublic());
        $proxyDef->setShared($serviceDef->isShared());
        $proxyDef->setFactory([new Reference(UnmanagedFactoryInstantiator::class), 'newInstance']);
        $proxyDef->setArgument(0, new Reference($wrappedServiceId));
        $serviceTags = new Tags($serviceClass, $serviceDef->getTags());
        $ufTags = $serviceTags->getUnmanagedFactoryTags();
        $proxyDef->setArgument(1, $ufTags->getFactoryMethods());
        $returnType = $ufTags->getFactoryReturnType($this->container);
        $this->finalProcessor->process($returnType);
        $proxyDef->setArgument(2, $returnType);

        $instanceLimit = (int) $this->container->getParameter(ContainerConstants::PARAM_COROUTINES_MAX_SVC_INSTANCES);
        $proxyDef->setArgument(3, $instanceLimit);

        foreach ($serviceTags as $tag => $attributes) {
            $proxyDef->addTag($tag, $attributes[0]);
        }

        return $proxyDef;
    }
}
