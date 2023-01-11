<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service;

final class ShouldBeProxified
{
    private AlwaysReset $dummy;

    public function __construct(AlwaysReset $dummy)
    {
        $this->dummy = $dummy;
    }

    public function wasDummyReset(): bool
    {
        return $this->dummy->getWasReset();
    }
}
