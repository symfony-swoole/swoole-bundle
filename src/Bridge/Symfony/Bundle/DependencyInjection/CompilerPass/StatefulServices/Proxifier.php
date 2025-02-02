<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use Doctrine\ORM\EntityManager;
use RuntimeException;
use SwooleBundle\SwooleBundle\Bridge\Doctrine\ORM\EntityManagerStabilityChecker;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Instantiator;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\DiServicePool;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\SimpleResetter;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\StabilityChecker;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Component\Locking\Channel\ChannelMutex;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use UnexpectedValueException;

final class Proxifier
{
    use ProxifierAssertions;

    private const DEFAULT_STABILITY_CHECKERS = [
        EntityManager::class => EntityManagerStabilityChecker::class,
    ];

    /**
     * @var array<Reference>
     */
    private array $proxifiedServicePoolRefs = [];

    /**
     * @var array<class-string, class-string<StabilityChecker>|string>
     */
    private array $stabilityCheckers;

    /**
     * @param array<class-string, class-string<StabilityChecker>|string> $stabilityCheckers
     */
    public function __construct(
        private readonly ContainerBuilder $container,
        private readonly FinalClassesProcessor $finalProcessor,
        array $stabilityCheckers = [],
    ) {
        $this->stabilityCheckers = array_merge(self::DEFAULT_STABILITY_CHECKERS, $stabilityCheckers);
    }

    public function proxifyService(string $serviceId, ?string $externalResetter = null): void
    {
        if (!$this->container->has($serviceId)) {
            throw new RuntimeException(sprintf('Service missing: %s', $serviceId));
        }

        $serviceDef = $this->container->findDefinition($serviceId);
        /** @var class-string $class */
        $class = $serviceDef->getClass();
        $tags = new Tags($class, $serviceDef->getTags());
        $this->assertServiceIsNotReadOnly($serviceId, $serviceDef);

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
            throw new RuntimeException(sprintf('Service missing: %s', $serviceId));
        }

        $wrappedServiceId = sprintf('%s.swoole_coop.wrapped', $serviceId);
        $svcPoolDef = $this->prepareServicePool($wrappedServiceId, $serviceDef, $externalResetter);
        $svcPoolServiceId = sprintf('%s.swoole_coop.service_pool', $serviceId);
        $wasShared = $serviceDef->isShared();
        $proxyDef = $this->prepareProxy($svcPoolServiceId, $serviceDef);
        $this->prepareProxifiedService($serviceDef);
        $serviceDef->clearTags();

        $this->container->setDefinition($svcPoolServiceId, $svcPoolDef);
        $this->container->setDefinition($serviceId, $proxyDef); // proxy swap
        $this->container->setDefinition($wrappedServiceId, $serviceDef); // old service for copying

        // new pools will be registered in the container on their instantiation
        if (!$wasShared) {
            return;
        }

        $this->proxifiedServicePoolRefs[] = new Reference($svcPoolServiceId);
    }

    private function doProxifyDecoratedService(
        string $serviceId,
        Definition $serviceDef,
        ?string $externalResetter = null,
    ): void {
        if ($serviceDef->innerServiceId === null) {
            throw new UnexpectedValueException(sprintf('Inner service id missing for service %s', $serviceId));
        }

        $decoratedServiceId = $serviceDef->innerServiceId;

        do {
            $decoratedServiceDef = $this->container->findDefinition($decoratedServiceId);

            if ($this->isProxyfiable($decoratedServiceId, $decoratedServiceDef)) {
                $this->doProxifyService($decoratedServiceId, $decoratedServiceDef, $externalResetter);

                return;
            }

            $decoratedServiceId = $decoratedServiceDef->innerServiceId;
        } while ($decoratedServiceDef !== null); /** @phpstan-ignore notIdentical.alwaysTrue */
    }

    private function prepareProxifiedService(Definition $serviceDef): void
    {
        /** @var class-string $className */
        $className = $serviceDef->getClass();
        $this->finalProcessor->process($className);
        $serviceDef->setPublic(true);
        $serviceDef->setShared(false);
    }

    private function prepareServicePool(
        string $wrappedServiceId,
        Definition $serviceDef,
        ?string $externalResetter = null,
    ): Definition {
        $svcPoolDef = new Definition(DiServicePool::class);
        $svcPoolDef->setShared($serviceDef->isShared());

        if (!$serviceDef->isShared()) {
            $svcPoolDef->setConfigurator([new Reference(NonSharedSvcPoolConfigurator::class), 'configure']);
        }

        $svcPoolDef->setArgument(0, $wrappedServiceId);
        $svcPoolDef->setArgument(1, new Reference('service_container'));
        $svcPoolDef->setArgument(2, new Reference(Swoole::class));
        $svcPoolDef->setArgument(3, $this->prepareServicePoolMutex());
        $instanceLimit = $this->container->getParameter(ContainerConstants::PARAM_COROUTINES_MAX_SVC_INSTANCES);

        if (!is_int($instanceLimit)) {
            throw new UnexpectedValueException(
                sprintf('Parameter %s must be an integer', ContainerConstants::PARAM_COROUTINES_MAX_SVC_INSTANCES)
            );
        }

        /** @var class-string $serviceClass */
        $serviceClass = $serviceDef->getClass();
        $serviceTags = new Tags($serviceClass, $serviceDef->getTags());
        $serviceTag = $serviceTags->findStatefulServiceTag();
        $customResetter = null;

        if ($serviceTag?->getLimit() !== null) {
            $instanceLimit = $serviceTag->getLimit();
        }

        if ($serviceTag?->getResetter() !== null) {
            $customResetter = $serviceTag->getResetter();
        }

        $svcPoolDef->setArgument(4, $instanceLimit);
        $svcPoolDef->setArgument(5, null);

        $resetterDefOrRef = null;

        if ($customResetter !== null) {
            $resetterDefOrRef = new Reference($customResetter);
        }

        if ($resetterDefOrRef === null && $externalResetter !== null) {
            $resetterDefOrRef = new Definition();
            $resetterDefOrRef->setClass(SimpleResetter::class);
            $resetterDefOrRef->setArgument(0, $externalResetter);
        }

        if ($resetterDefOrRef) {
            $svcPoolDef->setArgument(5, $resetterDefOrRef);
        }

        if (!isset($this->stabilityCheckers[$serviceClass])) {
            return $svcPoolDef;
        }

        $checkerSvcId = $this->stabilityCheckers[$serviceClass];
        $this->container->findDefinition($checkerSvcId);
        $svcPoolDef->setArgument(6, new Reference($checkerSvcId));

        return $svcPoolDef;
    }

    private function prepareServicePoolMutex(): Definition
    {
        $mutexDef = new Definition(ChannelMutex::class);
        $mutexDef->setFactory([new Reference('swoole_bundle.service_pool.locking'), 'newMutex']);

        return $mutexDef;
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

        $this->assertServiceIsNotReadOnly($serviceId, $serviceDef);
        $factory = $serviceDef->getFactory();

        if (!is_array($factory)) {
            return true;
        }

        $factorySvc = $factory[0];

        return !$factorySvc instanceof Reference || (string) $factorySvc !== Instantiator::class;
    }
}
