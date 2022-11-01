<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use Doctrine\ORM\EntityManager;
use K911\Swoole\Bridge\Doctrine\ORM\EntityManagerStabilityChecker;
use K911\Swoole\Bridge\Symfony\Container\Proxy\Instantiator;
use K911\Swoole\Bridge\Symfony\Container\ServicePool\DiServicePool;
use K911\Swoole\Bridge\Symfony\Container\StabilityChecker;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class Proxifier
{
    private const DEFAULT_STABILITY_CHECKERS = [
        EntityManager::class => EntityManagerStabilityChecker::class,
    ];

    private ContainerBuilder $container;

    private FinalClassesProcessor $finalProcessor;

    /**
     * @var array<Reference>
     */
    private $proxifiedServicePoolsRefs = [];

    /**
     * @var array<ChildDefinition>
     */
    private $resetterRefs = [];

    /**
     * @var array<class-string, class-string<StabilityChecker>|string>
     */
    private $stabilityCheckers;

    /**
     * @param array<class-string, class-string<StabilityChecker>|string> $stabilityCheckers
     */
    public function __construct(
        ContainerBuilder $container,
        FinalClassesProcessor $finalProcessor,
        array $stabilityCheckers = []
    ) {
        $this->container = $container;
        $this->finalProcessor = $finalProcessor;
        $this->stabilityCheckers = array_merge(self::DEFAULT_STABILITY_CHECKERS, $stabilityCheckers);
    }

    public function proxifyService(string $serviceId): void
    {
        if (!$this->container->has($serviceId)) {
            throw new \RuntimeException(sprintf('Service missing: %s', $serviceId));
        }

        $serviceDef = $this->container->findDefinition($serviceId);
        /** @var class-string $class */
        $class = $serviceDef->getClass();
        $tags = new Tags($class, $serviceDef->getTags());

        if ($tags->hasSafeStatefulServiceTag()) {
            return;
        }

        if (!$tags->hasDecoratedStatefulServiceTag()) {
            $this->doProxifyService($serviceId, $serviceDef);

            return;
        }

        $this->doProxifyDecoratedService($serviceId, $serviceDef);
    }

    public function getProxifiedServicePoolsRefs(): array
    {
        return $this->proxifiedServicePoolsRefs;
    }

    /**
     * @return array<ChildDefinition>
     */
    public function getResetterRefs(): array
    {
        return $this->resetterRefs;
    }

    private function doProxifyService(string $serviceId, Definition $serviceDef): void
    {
        if (!$this->container->has($serviceId)) {
            throw new \RuntimeException(sprintf('Service missing: %s', $serviceId));
        }

        $this->prepareProxifiedService($serviceDef);
        $wrappedServiceId = sprintf('%s.swoole_coop.wrapped', $serviceId);
        $svcPoolDef = $this->prepareServicePool($wrappedServiceId, $serviceDef);
        $svcPoolServiceId = sprintf('%s.swoole_coop.service_pool', $serviceId);
        $proxyDef = $this->prepareProxy($svcPoolServiceId, $serviceDef);
        $serviceDef->clearTags();

        $this->container->setDefinition($svcPoolServiceId, $svcPoolDef);
        $this->container->setDefinition($serviceId, $proxyDef); // proxy swap
        $this->container->setDefinition($wrappedServiceId, $serviceDef); // old service for copying

        $this->proxifiedServicePoolsRefs[] = new Reference($svcPoolServiceId);
    }

    private function doProxifyDecoratedService(string $serviceId, Definition $serviceDef): void
    {
        if (null === $serviceDef->innerServiceId) {
            throw new \UnexpectedValueException(sprintf('Inner service id missing for service %s', $serviceId));
        }

        $decoratedServiceId = $serviceDef->innerServiceId;

        do {
            $decoratedServiceDef = $this->container->findDefinition($decoratedServiceId);

            if ($this->isProxyfiable($decoratedServiceId, $decoratedServiceDef)) {
                $this->doProxifyService($decoratedServiceId, $decoratedServiceDef);

                return;
            }

            $decoratedServiceId = $decoratedServiceDef->innerServiceId;
        } while (null !== $decoratedServiceDef);
    }

    private function prepareProxifiedService(Definition $serviceDef): void
    {
        $this->finalProcessor->process($serviceDef->getClass());
        $serviceDef->setPublic(true);
        $serviceDef->setShared(false);
    }

    private function prepareServicePool(string $wrappedServiceId, Definition $serviceDef): Definition
    {
        $svcPoolDef = new Definition(DiServicePool::class);
        $svcPoolDef->setShared(true);
        $svcPoolDef->setArgument(0, $wrappedServiceId);
        $svcPoolDef->setArgument(1, new Reference('service_container'));
        $serviceClass = $serviceDef->getClass();

        if (!isset($this->stabilityCheckers[$serviceClass])) {
            return $svcPoolDef;
        }

        $checkerSvcId = $this->stabilityCheckers[$serviceClass];
        $this->container->findDefinition($checkerSvcId);
        $svcPoolDef->setArgument(2, new Reference($checkerSvcId));

        return $svcPoolDef;
    }

    private function prepareProxy(string $svcPoolServiceId, Definition $serviceDef): Definition
    {
        $serviceWasPublic = $serviceDef->isPublic();
        $serviceClass = $serviceDef->getClass();
        $proxyDef = new Definition($serviceClass);
        $proxyDef->setFactory([new Reference(Instantiator::class), 'newInstance']);
        $proxyDef->setPublic($serviceWasPublic);
        $proxyDef->setArgument(0, new Reference($svcPoolServiceId));
        $proxyDef->setArgument(1, $serviceClass);
        $serviceTags = $serviceDef->getTags();

        foreach ($serviceTags as $tag => $attributes) {
            $proxyDef->addTag($tag, $attributes[0]);
        }

        return $proxyDef;
    }

    private function isProxyfiable(string $serviceId, Definition $serviceDef): bool
    {
        $resetterDef = $this->container->findDefinition('services_resetter');

        /** @var IteratorArgument $resetters */
        $resetters = $resetterDef->getArgument(0);
        $resetterValues = $resetters->getValues();
        $isReset = isset($resetterValues[$serviceId]) || isset($resetterValues[$serviceDef->getClass()]);
        /** @var class-string $class */
        $class = $serviceDef->getClass();
        $tags = new Tags($class, $serviceDef->getTags());
        $hasStatefulServiceTag = $tags->hasStatefulServiceTag();

        if (!$isReset && !$hasStatefulServiceTag) {
            return false;
        }

        $factory = $serviceDef->getFactory();

        if (!is_array($factory)) {
            return true;
        }

        $factorySvc = $factory[0];

        return !$factorySvc instanceof Reference || Instantiator::class !== (string) $factorySvc;
    }
}
