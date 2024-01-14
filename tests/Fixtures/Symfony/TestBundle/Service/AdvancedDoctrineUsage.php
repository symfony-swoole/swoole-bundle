<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Ramsey\Uuid\UuidFactoryInterface;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Entity\AdvancedTest;

final class AdvancedDoctrineUsage
{
    public function __construct(
        private readonly UuidFactoryInterface $uuidFactory,
        private readonly Registry $doctrine
    ) {
    }

    public function run(): int
    {
        $em = $this->doctrine->getManager();
        $newEntity = new AdvancedTest($this->uuidFactory->uuid4());

        for ($i = 0; $i < 10; ++$i) {
            $incr = $newEntity->increment();
            $em->persist($newEntity);
            $em->flush();
        }

        return $incr;
    }
}
