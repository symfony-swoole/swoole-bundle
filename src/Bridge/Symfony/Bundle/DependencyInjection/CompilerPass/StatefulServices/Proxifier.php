<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use Assert\Assertion;
use Doctrine\ORM\EntityManager;
use RuntimeException;
use SwooleBundle\SwooleBundle\Bridge\Doctrine\ORM\EntityManagerStabilityChecker;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Instantiator;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\DiServicePool;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolEntry;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\SimpleResetter;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\StabilityChecker;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Component\Locking\Channel\ChannelMutex;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use UnexpectedValueException;

final class Proxifier implements ServiceProxifier
{
    use ProxifierAssertions;

    private const array DEFAULT_STABILITY_CHECKERS = [
        EntityManager::class => EntityManagerStabilityChecker::class,
    ];

    /**
     * @var array<int, Definition>
     */
    private array $proxifiedServicePoolEntryDefs = [];

    /**
     * @var array<class-string, class-string<StabilityChecker>|string>
     */
    private array $stabilityCheckers;

    /**
     * @param array<class-string, class-string<StabilityChecker>|string> $stabilityCheckers
     */
    public function __construct(
        private readonly ContainerBuilder $container,
        private readonly ClassModificationProcessor $modificationProcessor,
        array $stabilityCheckers = [],
    ) {
        $this->stabilityCheckers = array_merge(self::DEFAULT_STABILITY_CHECKERS, $stabilityCheckers);
    }

    public function proxifyService(string $serviceId, ?string $externalResetter = null, int $resetPriority = 0): void
    {
        if (!$this->container->has($serviceId)) {
            throw new RuntimeException(sprintf('Service missing: %s', $serviceId));
        }

        $this->assertNotAlreadyProxified($serviceId);

        $serviceDef = $this->container->findDefinition($serviceId);
        /** @var class-string $class */
        $class = $serviceDef->getClass();
        $tags = new Tags($class, $serviceDef->getTags());

        if ($this->isDefinedByInterfaceOnly($serviceDef)) {
            return;
        }

        $this->assertServiceIsNotReadOnly($serviceId, $serviceDef);

        if ($tags->hasSafeStatefulServiceTag()) {
            return;
        }

        if (!$tags->hasDecoratedStatefulServiceTag()) {
            $this->doProxifyService($serviceId, $serviceDef, $externalResetter, $resetPriority);

            return;
        }

        $this->doProxifyDecoratedService($serviceId, $serviceDef, $externalResetter);
    }

    /**
     * @return array<int, Definition>
     */
    public function getProxifiedServicePoolEntryDefs(): array
    {
        krsort($this->proxifiedServicePoolEntryDefs);

        return $this->proxifiedServicePoolEntryDefs;
    }

    private function doProxifyService(
        string $serviceId,
        Definition $serviceDef,
        ?string $externalResetter = null,
        int $resetPriority = 0,
    ): void {
        if (!$this->container->has($serviceId)) {
            throw new RuntimeException(sprintf('Service missing: %s', $serviceId));
        }

        /** @var class-string $serviceClass */
        $serviceClass = $serviceDef->getClass();
        $serviceTags = new Tags($serviceClass, $serviceDef->getTags());

        $wrappedServiceId = sprintf('%s.swoole_coop.wrapped', $serviceId);
        $svcPoolDef = $this->prepareServicePool($wrappedServiceId, $serviceDef, $serviceTags);
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

        $customResetter = null;
        $serviceTag = $serviceTags->findStatefulServiceTag();

        if ($serviceTag?->getResetter() !== null) {
            $customResetter = $serviceTag->getResetter();
        }

        $resetterDefOrRef = null;

        if ($customResetter !== null) {
            $resetterDefOrRef = new Reference($customResetter);
        }

        if ($resetterDefOrRef === null && $externalResetter !== null) {
            $resetterDefOrRef = new Definition();
            $resetterDefOrRef->setClass(SimpleResetter::class);
            $resetterDefOrRef->setArgument(0, $externalResetter);
        }

        $this->registerProxifiedServicePoolEntryDefinition(
            new Reference($svcPoolServiceId),
            $resetterDefOrRef,
            $resetPriority,
        );
    }

    private function doProxifyDecoratedService(
        string $serviceId,
        Definition $serviceDef,
        ?string $externalResetter = null,
        int $resetPriority = 0,
    ): void {
        if ($serviceDef->innerServiceId === null) {
            throw new UnexpectedValueException(sprintf('Inner service id missing for service %s', $serviceId));
        }

        $decoratedServiceId = $serviceDef->innerServiceId;

        do {
            $decoratedServiceDef = $this->container->findDefinition($decoratedServiceId);

            if ($this->isProxyfiable($decoratedServiceId, $decoratedServiceDef)) {
                $this->doProxifyService($decoratedServiceId, $decoratedServiceDef, $externalResetter, $resetPriority);

                return;
            }

            $decoratedServiceId = $decoratedServiceDef->innerServiceId;
            Assertion::string($decoratedServiceId);
        } while ($decoratedServiceDef !== null); /** @phpstan-ignore notIdentical.alwaysTrue */
    }

    private function prepareProxifiedService(Definition $serviceDef): void
    {
        /** @var class-string $className */
        $className = $serviceDef->getClass();
        $this->modificationProcessor->processFinalClass($className);
        $serviceDef->setPublic(true);
        $serviceDef->setShared(false);
    }

    private function prepareServicePool(string $wrappedServiceId, Definition $serviceDef, Tags $serviceTags): Definition
    {
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

        $serviceTag = $serviceTags->findStatefulServiceTag();
        $serviceClass = $serviceDef->getClass();

        if ($serviceTag?->getLimit() !== null) {
            $instanceLimit = $serviceTag->getLimit();
        }

        $svcPoolDef->setArgument(4, $instanceLimit);

        $stabilityCheckerRef = null;

        if (isset($this->stabilityCheckers[$serviceClass])) {
            $checkerSvcId = $this->stabilityCheckers[$serviceClass];
            $this->container->findDefinition($checkerSvcId);
            $stabilityCheckerRef = new Reference($checkerSvcId);
        }

        $svcPoolDef->setArgument(5, $stabilityCheckerRef);

        if ($serviceTag?->getInitializer() !== null) {
            $svcPoolDef->setArgument(6, new Reference($serviceTag->getInitializer()));
        }

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
            foreach ($attributes as $attributeGroup) {
                $proxyDef->addTag($tag, $attributeGroup);
            }
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

        if ($this->isDefinedByInterfaceOnly($serviceDef)) {
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

    private function registerProxifiedServicePoolEntryDefinition(
        Reference $ref,
        Definition|Reference|null $resetterDefOrRef = null,
        int $resetPriority = 0,
    ): void {
        $recordDef = new Definition(ServicePoolEntry::class);
        $recordDef->setArgument(0, $ref);

        if ($resetterDefOrRef !== null) {
            $recordDef->setArgument(1, $resetterDefOrRef);
            $recordDef->setArgument(2, $resetPriority);
        }

        $this->proxifiedServicePoolEntryDefs[] = $recordDef;
    }

    /**
     * Refuses to pool a service that is already pooled.
     *
     * Proxifying twice wraps the proxy in another proxy, and it is the second pool entry that decides
     * how the service is reset - including deciding that it is not, since a caller which knows nothing
     * about resetting passes null, and ServicePoolContainer skips a pool entry without a resetter. The
     * service then quietly stops being reset, a long way from wherever the second call was written.
     *
     * Tagging is how a service asks to be pooled, and StatefulServicesPass acts on the tags after the
     * compile processors have run. A processor that tags a service has already asked for it; asking
     * again through here is the mistake this reports.
     *
     * Asked of the container rather than of a property, so it holds across however many Proxifier
     * instances one compile uses - StatefulServicesPass builds a second for unmanaged factories.
     */
    private function assertNotAlreadyProxified(string $serviceId): void
    {
        if (!$this->container->has(sprintf('%s.swoole_coop.wrapped', $serviceId))) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Service "%s" is already proxified and must not be proxified again. It is most likely both '
            . 'tagged "%s" and passed to proxifyService() explicitly - the tag alone is enough, and is '
            . 'acted on after the compile processors have run.',
            $serviceId,
            ContainerConstants::TAG_STATEFUL_SERVICE,
        ));
    }
}
