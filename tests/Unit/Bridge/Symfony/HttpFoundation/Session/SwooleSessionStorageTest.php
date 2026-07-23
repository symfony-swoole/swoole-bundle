<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\HttpFoundation\Session;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\SwooleSessionStorage;
use SwooleBundle\SwooleBundle\Server\Session\Storage;

final class SwooleSessionStorageTest extends TestCase
{
    public function testSaveTriggersGarbageCollectionWhenProbabilityMatches(): void
    {
        $storage = $this->createMock(Storage::class);
        $subject = new SwooleSessionStorage($storage, SwooleSessionStorage::DEFAULT_SESSION_NAME, 86400, 100, 100);
        $subject->start();

        $storage->expects($this->once())->method('set');
        $storage->expects($this->once())->method('garbageCollect');

        $subject->save();
    }

    public function testSaveDoesNotTriggerGarbageCollectionWhenProbabilityIsZero(): void
    {
        $storage = $this->createMock(Storage::class);
        $subject = new SwooleSessionStorage($storage, SwooleSessionStorage::DEFAULT_SESSION_NAME, 86400, 0, 100);
        $subject->start();

        $storage->expects($this->once())->method('set');
        $storage->expects($this->never())->method('garbageCollect');

        $subject->save();
    }

    public function testDefaultGcValuesAreOnePercentProbability(): void
    {
        $subject = new SwooleSessionStorage(
            $this->createStub(Storage::class),
            SwooleSessionStorage::DEFAULT_SESSION_NAME,
            86400
        );

        $reflection = new ReflectionClass($subject);

        $gcProbabilityProp = $reflection->getProperty('gcProbability');

        $gcDivisorProp = $reflection->getProperty('gcDivisor');

        // Default GC probability mirrors PHP's session.gc_probability (1) / session.gc_divisor (100) = 1%
        $this->assertSame(1, $gcProbabilityProp->getValue($subject));
        $this->assertSame(100, $gcDivisorProp->getValue($subject));
    }
}
