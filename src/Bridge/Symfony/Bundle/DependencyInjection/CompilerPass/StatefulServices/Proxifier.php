<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use Doctrine\ORM\EntityManager;
use K911\Swoole\Bridge\Doctrine\ORM\EntityManagerStabilityChecker;
use K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use K911\Swoole\Bridge\Symfony\Container\Proxy\Instantiator;
use K911\Swoole\Bridge\Symfony\Container\ServicePool\DiServicePool;
use K911\Swoole\Bridge\Symfony\Container\SimpleResetter;
use K911\Swoole\Bridge\Symfony\Container\StabilityChecker;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
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
    private $proxifiedServicePoolRefs = [];

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

    public function proxifyService(string $serviceId, ?string $externalResetter = null): void
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
            $this->doProxifyService($serviceId, $serviceDef, $externalResetter);

            return;
        }

        $this->doProxifyDecoratedService($serviceId, $serviceDef, $externalResetter);
    }

    /**
     * @return array<Reference>
     */
    public function getProxifiedServicePoolRefs(): array
    {
        return $this->proxifiedServicePoolRefs;
    }

    private function doProxifyService(string $serviceId, Definition $serviceDef, ?string $externalResetter = null): void
    {
        if (!$this->container->has($serviceId)) {
            throw new \RuntimeException(sprintf('Service missing: %s', $serviceId));
        }

        $wrappedServiceId = sprintf('%s.swoole_coop.wrapped', $serviceId);
        $svcPoolDef = $this->prepareServicePool($wrappedServiceId, $serviceDef, $externalResetter);
        $svcPoolServiceId = sprintf('%s.swoole_coop.service_pool', $serviceId);
        $proxyDef = $this->prepareProxy($svcPoolServiceId, $serviceDef);
        $this->prepareProxifiedService($serviceDef);
        $serviceDef->clearTags();

        $this->container->setDefinition($svcPoolServiceId, $svcPoolDef);
        $this->container->setDefinition($serviceId, $proxyDef); // proxy swap
        $this->container->setDefinition($wrappedServiceId, $serviceDef); // old service for copying

        $this->proxifiedServicePoolRefs[] = new Reference($svcPoolServiceId);
    }

    private function doProxifyDecoratedService(string $serviceId, Definition $serviceDef, ?string $externalResetter = null): void
    {
        if (null === $serviceDef->innerServiceId) {
            throw new \UnexpectedValueException(sprintf('Inner service id missing for service %s', $serviceId));
        }

        $decoratedServiceId = $serviceDef->innerServiceId;

        do {
            $decoratedServiceDef = $this->container->findDefinition($decoratedServiceId);

            if ($this->isProxyfiable($decoratedServiceId, $decoratedServiceDef)) {
                $this->doProxifyService($decoratedServiceId, $decoratedServiceDef, $externalResetter);

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

    private function prepareServicePool(
        string $wrappedServiceId,
        Definition $serviceDef,
        ?string $externalResetter = null
    ): Definition {
        $svcPoolDef = new Definition(DiServicePool::class);
        $svcPoolDef->setShared($serviceDef->isShared());
        $svcPoolDef->setArgument(0, $wrappedServiceId);
        $svcPoolDef->setArgument(1, new Reference('service_container'));
        $svcPoolDef->setArgument(2, new Reference('swoole_bundle.service_pool.locking'));
        $instanceLimit = (int) $this->container->getParameter(ContainerConstants::PARAM_COROUTINES_MAX_SVC_INSTANCES);
        /** @var class-string $serviceClass */
        $serviceClass = $serviceDef->getClass();
        $serviceTags = new Tags($serviceClass, $serviceDef->getTags());
        $serviceTag = $serviceTags->findStatefulServiceTag();
        $customResetter = null;

        if (null !== $serviceTag && null !== $serviceTag->getLimit()) {
            $instanceLimit = $serviceTag->getLimit();
            $customResetter = $serviceTag->getResetter();
        }

        $svcPoolDef->setArgument(3, $instanceLimit);
        $svcPoolDef->setArgument(4, null);

        $resetterDefOrRef = null;

        if (null !== $customResetter) {
            $resetterDefOrRef = new Reference($customResetter);
        }

        if (null === $resetterDefOrRef && null !== $externalResetter) {
            $resetterDefOrRef = new Definition();
            $resetterDefOrRef->setClass(SimpleResetter::class);
            $resetterDefOrRef->setArgument(0, $externalResetter);
        }

        if ($resetterDefOrRef) {
            $svcPoolDef->setArgument(4, $resetterDefOrRef);
        }

        if (!isset($this->stabilityCheckers[$serviceClass])) {
            return $svcPoolDef;
        }

        $checkerSvcId = $this->stabilityCheckers[$serviceClass];
        $this->container->findDefinition($checkerSvcId);
        $svcPoolDef->setArgument(5, new Reference($checkerSvcId));

        return $svcPoolDef;
    }

    private function prepareProxy(string $svcPoolServiceId, Definition $serviceDef): Definition
    {
        $serviceWasPublic = $serviceDef->isPublic();
        $serviceWasShared = $serviceDef->isShared();
        $serviceClass = $serviceDef->getClass();
        $proxyDef = new Definition($serviceClass);
        $proxyDef->setFactory([new Reference(Instantiator::class), 'newInstance']);
        $proxyDef->setPublic($serviceWasPublic);
        $proxyDef->setShared($serviceWasShared);
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
