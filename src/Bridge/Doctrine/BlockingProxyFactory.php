<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Doctrine;

use Composer\InstalledVersions;
use Doctrine\Common\Proxy\Proxy;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Proxy\InternalProxy;
use Doctrine\ORM\Proxy\ProxyFactory;
use Override;
use SwooleBundle\SwooleBundle\Component\Locking\FirstTimeOnly\FirstTimeOnlyMutex;
use SwooleBundle\SwooleBundle\Component\Locking\FirstTimeOnly\FirstTimeOnlyMutexFactory;

if (version_compare(InstalledVersions::getVersion('doctrine/orm'), '3.0.0', '<')) {
    final class BlockingProxyFactory extends ProxyFactory
    {
        /**
         * @var array<string, FirstTimeOnlyMutex>
         */
        private array $mutexes = [];

        public function __construct(
            private readonly ProxyFactory $wrapped,
            private readonly FirstTimeOnlyMutexFactory $mutexFactory,
        ) {}

        /**
         * @template T of object
         * @param class-string<T> $className
         * @param array<mixed> $identifier
         * @return Proxy<T>
         */
        #[Override]
        public function getProxy($className, array $identifier): Proxy
        {
            $mutex = $this->getMutex($className);

            try {
                $mutex->acquire();
                $proxy = $this->wrapped->getProxy($className, $identifier);
            } finally {
                $mutex->release();
            }

            return $proxy;
        }

        /**
         * @param array<ClassMetadata<object>> $classes
         * @param string|null $proxyDir
         */
        // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
        #[Override]
        public function generateProxyClasses(array $classes, $proxyDir = null): int
        {
            return $this->wrapped->generateProxyClasses($classes, $proxyDir);
        }

        /**
         * @template T of object
         * @param Proxy<T> $proxy
         * @return Proxy<T>
         */
        public function resetUninitializedProxy(Proxy $proxy): Proxy
        {
            return $this->wrapped->resetUninitializedProxy($proxy);
        }

        private function getMutex(string $className): FirstTimeOnlyMutex
        {
            if (!isset($this->mutexes[$className])) {
                $this->mutexes[$className] = $this->mutexFactory->newMutex();
            }

            return $this->mutexes[$className];
        }
    }
} else {
    final class BlockingProxyFactory extends ProxyFactory
    {
        /**
         * @var array<string, FirstTimeOnlyMutex>
         */
        private array $mutexes = [];

        public function __construct(
            private readonly ProxyFactory $wrapped,
            private readonly FirstTimeOnlyMutexFactory $mutexFactory,
        ) {}

        /**
         * @template T of object
         * @param class-string<T> $className
         * @param array<mixed> $identifier
         * @return InternalProxy<T>
         */
        #[Override]
        public function getProxy(string $className, array $identifier, bool $assignIdentifiers = true): InternalProxy
        {
            // $assignIdentifiers arrived with doctrine/orm 3.7, and a subclass may not drop a parameter its parent
            // declares: without it the class fails to load at all on 3.7 - "Declaration of ...::getProxy() must be
            // compatible with ...". It is optional, so the signature stays compatible with 3.0-3.6, which do not
            // have it, and it is forwarded unconditionally, which a 3.6 factory simply ignores.
            $mutex = $this->getMutex($className);

            try {
                $mutex->acquire();
                $proxy = $this->wrapped->getProxy($className, $identifier, $assignIdentifiers);
            } finally {
                $mutex->release();
            }

            return $proxy;
        }

        /**
         * @param array<ClassMetadata<object>> $classes
         * @param string|null $proxyDir
         */
        // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
        #[Override]
        public function generateProxyClasses(array $classes, $proxyDir = null): int
        {
            return $this->wrapped->generateProxyClasses($classes, $proxyDir);
        }

        private function getMutex(string $className): FirstTimeOnlyMutex
        {
            if (!isset($this->mutexes[$className])) {
                $this->mutexes[$className] = $this->mutexFactory->newMutex();
            }

            return $this->mutexes[$className];
        }
    }
}
