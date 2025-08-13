<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Doctrine;

use Assert\Assertion;
use Composer\InstalledVersions;
use Doctrine\Bundle\DoctrineBundle\ManagerConfigurator;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use ReflectionProperty;
use SwooleBundle\SwooleBundle\Component\Locking\FirstTimeOnly\FirstTimeOnlyMutexFactory;
use UnexpectedValueException;

final class BlockingProxyFactoryOverridingManagerConfigurator
{
    private static ?ReflectionProperty $emProxyFactoryPropRefl = null;

    public function __construct(
        private readonly ManagerConfigurator $wrapped,
        private readonly FirstTimeOnlyMutexFactory $mutexFactory,
    ) {}

    public function configure(EntityManagerInterface $entityManager): void
    {
        if (!$entityManager instanceof EntityManager) {
            throw new UnexpectedValueException(
                sprintf('%s needed, got %s.', EntityManager::class, $entityManager::class)
            );
        }

        $ormVersion = InstalledVersions::getVersion('doctrine/orm');
        Assertion::string($ormVersion);

        if (
            version_compare($ormVersion, '3.4.0', '<')
            || !$entityManager->getConfiguration()->isNativeLazyObjectsEnabled()
        ) {
            $this->replaceProxyFactory($entityManager);
        }
        $this->wrapped->configure($entityManager);
    }

    private function replaceProxyFactory(EntityManager $entityManager): void
    {
        $proxyFactory = new BlockingProxyFactory($entityManager->getProxyFactory(), $this->mutexFactory);
        $proxyFactoryProp = $this->getEmProxyFactoryReflectionProperty();
        $proxyFactoryProp->setValue($entityManager, $proxyFactory);
    }

    private function getEmProxyFactoryReflectionProperty(): ReflectionProperty
    {
        if (self::$emProxyFactoryPropRefl === null) {
            $emReflClass = new ReflectionClass(EntityManager::class);
            self::$emProxyFactoryPropRefl = $emReflClass->getProperty('proxyFactory');
            self::$emProxyFactoryPropRefl->setAccessible(true);
        }

        return self::$emProxyFactoryPropRefl;
    }
}
