<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service;

final class ShouldBeProxified
{
    public function __construct(
        private readonly AlwaysReset $dummy,
        private readonly AlwaysResetSafe $safeDummy
    ) {
    }

    public function wasDummyReset(): bool
    {
        return $this->dummy->getWasReset();
    }

    public function getSafeDummy(): AlwaysResetSafe
    {
        return $this->safeDummy;
    }
}
