<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Functions;

use PHPUnit\Framework\TestCase;

use function SwooleBundle\SwooleBundle\replace_object_property;

final class ReplaceObjectPropertyTest extends TestCase
{
    /**
     * @var TestObject
     */
    private $testObject;

    protected function setUp(): void
    {
        $this->testObject = new TestObject();
    }

    public function testReplacePublicProperty(): void
    {
        self::assertSame(TestObject::WRONG_VALUE, $this->testObject->getPublicProp());

        replace_object_property($this->testObject, 'publicProp', TestObject::GOOD_VALUE);

        self::assertSame(TestObject::GOOD_VALUE, $this->testObject->getPublicProp());
    }

    public function testReplaceProtectedProperty(): void
    {
        self::assertSame(TestObject::WRONG_VALUE, $this->testObject->getPublicProp());

        replace_object_property($this->testObject, 'protectedProp', TestObject::GOOD_VALUE);

        self::assertSame(TestObject::GOOD_VALUE, $this->testObject->getProtectedProp());
    }

    public function testReplacePrivateProperty(): void
    {
        self::assertSame(TestObject::WRONG_VALUE, $this->testObject->getPublicProp());

        replace_object_property($this->testObject, 'privateProp', TestObject::GOOD_VALUE);

        self::assertSame(TestObject::GOOD_VALUE, $this->testObject->getPrivateProp());
    }

    public function testReplaceDynamicProperty(): void
    {
        self::assertSame(TestObject::WRONG_VALUE, $this->testObject->getPublicProp());

        replace_object_property($this->testObject, 'dynamicProp', TestObject::GOOD_VALUE);

        self::assertSame(TestObject::GOOD_VALUE, $this->testObject->getDynamicProp());
    }
}
