<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Functions;

use AllowDynamicProperties;

/**
 * @property string $dynamicProp
 */
#[AllowDynamicProperties]
final class TestObject
{
    final public const GOOD_VALUE = 'good';
    final public const WRONG_VALUE = 'wrong';

    public string $publicProp; // phpcs:ignore
    protected string $protectedProp;

    public function __construct(
        private string $privateProp = self::WRONG_VALUE,
    ) {
        $this->protectedProp = $privateProp;
        $this->publicProp = $privateProp;
        $this->dynamicProp = $privateProp;
    }

    public function getPrivateProp(): string
    {
        return $this->privateProp;
    }

    public function getProtectedProp(): string
    {
        return $this->protectedProp;
    }

    public function getPublicProp(): string
    {
        return $this->publicProp;
    }

    public function getDynamicProp(): string
    {
        return $this->dynamicProp;
    }
}
