<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Functions;

/**
 * Class TestObject.
 *
 * @property string $dynamicProp
 */
#[\AllowDynamicProperties]
class TestObject
{
    final public const GOOD_VALUE = 'good';
    final public const WRONG_VALUE = 'wrong';
    public $publicProp;
    protected $protectedProp;

    public function __construct(private string $privateProp = self::WRONG_VALUE)
    {
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
