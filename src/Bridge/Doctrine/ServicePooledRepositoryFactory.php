<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Doctrine;

use Composer\InstalledVersions;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Repository\RepositoryFactory;
use Doctrine\Persistence\ObjectRepository;

if (version_compare(InstalledVersions::getVersion('doctrine/orm'), '3.0.0', '<')) {
    final readonly class ServicePooledRepositoryFactory implements RepositoryFactory
    {
        public function __construct(
            private RepositoryFactory $decorated,
            private EntityManagerInterface $pooledEm,
        ) {}

        /**
         * {@inheritDoc}
         */
        public function getRepository(EntityManagerInterface $entityManager, $entityName): ObjectRepository
        {
            return $this->decorated->getRepository($this->pooledEm, $entityName);
        }
    }
} else {
    final readonly class ServicePooledRepositoryFactory implements RepositoryFactory
    {
        public function __construct(
            private RepositoryFactory $decorated,
            private EntityManagerInterface $pooledEm,
        ) {}

        /**
         * {@inheritDoc}
         */
        public function getRepository(EntityManagerInterface $entityManager, $entityName): EntityRepository
        {
            return $this->decorated->getRepository($this->pooledEm, $entityName);
        }
    }
}
