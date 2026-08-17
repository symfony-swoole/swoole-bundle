<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\HttpFoundation\Session;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\SwooleSessionStorage;
use SwooleBundle\SwooleBundle\Server\Session\Storage;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

final class SwooleSessionStorageTest extends TestCase
{
    /**
     * A session bag holds whatever the application puts in it, and the security component puts the
     * failed login in there for the login page to read back:
     *
     * ```php
     * $authenticationException = $session->get(SecurityRequestAttributes::AUTHENTICATION_ERROR);
     * ```
     *
     * Stored as JSON this survived the write and the read and came back an array, which the reader
     * then handed to a caller expecting an exception.
     */
    // phpcs:disable SlevomatCodingStandard.PHP.DisallowReference
    public function testAnObjectPutInTheSessionComesBackAnObject(): void
    {
        $stored = null;
        $storage = $this->createStub(Storage::class);
        $storage->method('set')->willReturnCallback(
            static function (string $key, mixed $data) use (&$stored): void {
                $stored = $data;
            },
        );
        $storage->method('get')->willReturnCallback(static function () use (&$stored): mixed {
            return $stored;
        });

        $bag = new AttributeBag();
        $bag->setName('attributes');
        $subject = new SwooleSessionStorage($storage, SwooleSessionStorage::DEFAULT_SESSION_NAME, 86400, 0);
        $subject->registerBag($bag);
        $subject->start();
        $bag->set('_security.last_error', new BadCredentialsException('Invalid credentials.'));
        $subject->save();

        $readBag = new AttributeBag();
        $readBag->setName('attributes');
        $reader = new SwooleSessionStorage($storage, SwooleSessionStorage::DEFAULT_SESSION_NAME, 86400, 0);
        $reader->registerBag($readBag);
        $reader->setId($subject->getId());
        $reader->start();

        $error = $readBag->get('_security.last_error');

        self::assertInstanceOf(BadCredentialsException::class, $error);
        self::assertSame('Invalid credentials.', $error->getMessage());
    }

    /**
     * Sessions written before the storage format changed are JSON, and everything an application put in
     * them beyond a scalar came back the wrong shape. Handing that data on would carry the bug over the
     * upgrade; a session that starts over does not.
     */
    public function testASessionLeftOverFromTheJsonFormatIsStartedOver(): void
    {
        $storage = $this->createStub(Storage::class);
        $storage->method('get')->willReturn(json_encode(['_sf2_attributes' => ['luckyNumber' => 7]]));

        $bag = new AttributeBag();
        $bag->setName('attributes');
        $subject = new SwooleSessionStorage($storage, SwooleSessionStorage::DEFAULT_SESSION_NAME, 86400, 0);
        $subject->registerBag($bag);
        $subject->start();

        self::assertFalse($bag->has('luckyNumber'));
    }

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
