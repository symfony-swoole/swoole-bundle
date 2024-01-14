<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\UnmanagedFactoryInstantiator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class UnmanagedFactoryProxifier
{
    public function __construct(
        private readonly ContainerBuilder $container,
        private readonly FinalClassesProcessor $finalProcessor
    ) {
    }

    /**
     * returns new service id of the proxified service.
     */
    public function proxifyService(string $serviceId, ?string $externalResetter = null): string
    {
        if (!$this->container->has($serviceId)) {
            throw new \RuntimeException(sprintf('Service missing: %s', $serviceId));
        }

        $serviceDef = $this->prepareProxifiedService($serviceId);
        $wrappedServiceId = sprintf('%s.swoole_coop.wrapped_factory', $serviceId);
        $proxyDef = $this->prepareProxy($wrappedServiceId, $serviceDef, $externalResetter);
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

    private function prepareProxy(
        string $wrappedServiceId,
        Definition $serviceDef,
        ?string $externalResetter = null
    ): Definition {
        /** @var class-string $serviceClass */
        $serviceClass = $serviceDef->getClass();
        $proxyDef = new Definition($serviceClass);
        $proxyDef->setPublic($serviceDef->isPublic());
        $proxyDef->setShared($serviceDef->isShared());
        $proxyDef->setFactory([new Reference(UnmanagedFactoryInstantiator::class), 'newInstance']);
        $proxyDef->setArgument(0, new Reference($wrappedServiceId));
        $serviceTags = new Tags($serviceClass, $serviceDef->getTags());
        $ufTags = $serviceTags->getUnmanagedFactoryTags();
        $factoryConfigs = $ufTags->getFactoryMethodConfigs($this->container);

        $factoryConfigs = array_map(function (array $factoryConfig): array {
            if (!isset($factoryConfig['resetter'])) {
                return $factoryConfig;
            }

            $customResetter = $factoryConfig['resetter'];
            $resetterRef = new Reference($customResetter);
            $factoryConfig['resetter'] = $resetterRef;

            return $factoryConfig;
        }, $factoryConfigs);

        $proxyDef->setArgument(1, $factoryConfigs);

        foreach ($factoryConfigs as $factoryConfig) {
            $this->finalProcessor->process($factoryConfig['returnType']);
        }

        $instanceLimit = $this->container->getParameter(ContainerConstants::PARAM_COROUTINES_MAX_SVC_INSTANCES);

        if (!is_int($instanceLimit)) {
            throw new \UnexpectedValueException(sprintf('Parameter %s must be an integer', ContainerConstants::PARAM_COROUTINES_MAX_SVC_INSTANCES));
        }

        $proxyDef->setArgument(2, $instanceLimit);

        foreach ($serviceTags as $tag => $attributes) {
            $proxyDef->addTag($tag, $attributes[0]);
        }

        return $proxyDef;
    }
}
