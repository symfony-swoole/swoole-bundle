<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Ramsey\Uuid\UuidFactoryInterface;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Entity\Test;
use Symfony\Contracts\Service\ResetInterface;
use UnexpectedValueException;

final class DefaultDummyService implements ResetInterface, DummyService
{
    private readonly InMemoryRepository $tmpRepository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UuidFactoryInterface $uuidFactory,
        private readonly RepositoryFactory $factory,
    ) {
        $this->tmpRepository = $this->factory->newInstance();
    }

    /**
     * @return array<Test>
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
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
