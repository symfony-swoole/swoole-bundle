<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Entity\AdvancedTest;
use Ramsey\Uuid\UuidFactoryInterface;

final class AdvancedDoctrineUsage
{
    private UuidFactoryInterface $uuidFactory;

    private Registry $doctrine;

    public function __construct(UuidFactoryInterface $uuidFactory, Registry $doctrine)
    {
        $this->uuidFactory = $uuidFactory;
        $this->doctrine = $doctrine;
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
