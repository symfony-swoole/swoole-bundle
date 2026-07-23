<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service;

/**
 * cannot be readonly
 */
// phpcs:ignore SlevomatCodingStandard.Classes.ReadonlyClass
final class ShouldBeProxified
{
    public function __construct(
        private readonly AlwaysReset $dummy,
        private readonly AlwaysResetSafe $safeDummy,
    ) {}

    public function wasDummyReset(): bool
    {
        return $this->dummy->getWasReset();
    }

    public function getSafeDummy(): AlwaysResetSafe
    {
        return $this->safeDummy;
    }
}
