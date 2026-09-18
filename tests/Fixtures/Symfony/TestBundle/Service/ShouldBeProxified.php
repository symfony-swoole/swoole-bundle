<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service;

/**
 * Deliberately not readonly: the proxy of an ordinary class is what the tests using this cover. Readonly
 * services are covered by ReadonlyCurlClient.
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
