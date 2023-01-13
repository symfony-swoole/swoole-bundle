<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service;

final class ShouldBeProxified
{
    private AlwaysReset $dummy;

    private AlwaysResetSafe $safeDummy;

    public function __construct(AlwaysReset $dummy, AlwaysResetSafe $safeDummy)
    {
        $this->dummy = $dummy;
        $this->safeDummy = $safeDummy;
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
