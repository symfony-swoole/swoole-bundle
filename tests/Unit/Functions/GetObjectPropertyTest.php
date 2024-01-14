<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Functions;

use PHPUnit\Framework\TestCase;

use function SwooleBundle\SwooleBundle\get_object_property;

class GetObjectPropertyTest extends TestCase
{
    /**
     * @var TestObject
     */
    private $testObject;

    protected function setUp(): void
    {
        $this->testObject = new TestObject(TestObject::GOOD_VALUE);
    }

    public function testGetPublicProperty(): void
    {
        self::assertSame(TestObject::GOOD_VALUE, get_object_property($this->testObject, 'publicProp'));
    }

    public function testGetProtectedProperty(): void
    {
        self::assertSame(TestObject::GOOD_VALUE, get_object_property($this->testObject, 'protectedProp'));
    }

    public function testGetPrivateProperty(): void
    {
        self::assertSame(TestObject::GOOD_VALUE, get_object_property($this->testObject, 'privateProp'));
    }

    public function testGetDynamicProperty(): void
    {
        self::assertSame(TestObject::GOOD_VALUE, get_object_property($this->testObject, 'dynamicProp'));
    }
}
