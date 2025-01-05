<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

/**
 * @final
 */
#[ORM\Entity]
#[ORM\Table(name: 'advanced_test')]
class AdvancedTest // phpcs:ignore SlevomatCodingStandard.Classes.RequireAbstractOrFinal.ClassNeitherAbstractNorFinal
{
    #[ORM\Column(type: 'integer')]
    private int $counter = 0;

    public function __construct(
        /** @phpstan-ignore-next-line */
        #[ORM\Column(type: 'guid')]
        #[ORM\Id]
        private UuidInterface $uuid,
    ) {}

    public function getUuid(): UuidInterface
    {
        return $this->uuid;
    }

    public function increment(): int
    {
        $this->counter++;

        return $this->counter;
    }
}
