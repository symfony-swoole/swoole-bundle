<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Entity\Test;
use Ramsey\Uuid\UuidFactoryInterface;
use Symfony\Contracts\Service\ResetInterface;

final class DefaultDummyService implements ResetInterface, DummyService
{
    private EntityManagerInterface $entityManager;

    private UuidFactoryInterface $uuidFactory;

    private RepositoryFactory $factory;

    private InMemoryRepository $tmpRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        UuidFactoryInterface $uuidFactory,
        RepositoryFactory $factory
    ) {
        $this->entityManager = $entityManager;
        $this->uuidFactory = $uuidFactory;
        $this->factory = $factory;
        $this->tmpRepository = $this->factory->newInstance();
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     *
     * @return Test[]
     */
    public function process(): array
    {
        $test = new Test($this->uuidFactory->uuid4());
        $this->entityManager->persist($test);
        $this->entityManager->flush();
        $this->tmpRepository->store($test);

        return $this->entityManager->getRepository(Test::class)->findBy([], ['id' => 'desc'], 25);
    }

    public function reset(): void
    {
        $this->tmpRepository->reset();
    }

    public function getTmpRepository(): InMemoryRepository
    {
        return $this->tmpRepository;
    }
}
