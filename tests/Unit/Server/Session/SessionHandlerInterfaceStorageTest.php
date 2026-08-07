<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Session;

use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SessionHandlerInterface;
use stdClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\SessionHandlerInterfaceStorage;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\SwooleSessionStorage;
use SwooleBundle\SwooleBundle\Server\Session\Exception\LogicException;

final class SessionHandlerInterfaceStorageTest extends TestCase
{
    public function testSetDelegatesToWriteWithCorrectArgsAndIgnoresTtl(): void
    {
        $handler = $this->createMock(SessionHandlerInterface::class);
        $handler->expects($this->once())->method('open')->with('', SwooleSessionStorage::DEFAULT_SESSION_NAME);
        $handler->expects($this->once())->method('write')->with('session-id', 'serialized-data');
        $handler->expects($this->once())->method('close');

        $storage = new SessionHandlerInterfaceStorage($handler);
        $storage->set('session-id', 'serialized-data', 12345);
    }

    public function testSetThrowsWhenDataIsNotString(): void
    {
        $handler = $this->createMock(SessionHandlerInterface::class);
        $handler->expects($this->never())->method('open');
        $handler->expects($this->never())->method('write');
        $handler->expects($this->never())->method('close');

        $storage = new SessionHandlerInterfaceStorage($handler);

        $this->expectException(InvalidArgumentException::class);
        $storage->set('session-id', ['not-a-string'], 12345);
    }

    public function testDeleteDelegatesToDestroy(): void
    {
        $handler = $this->createMock(SessionHandlerInterface::class);
        $handler->expects($this->once())->method('open')->with('', SwooleSessionStorage::DEFAULT_SESSION_NAME);
        $handler->expects($this->once())->method('destroy')->with('session-id');
        $handler->expects($this->once())->method('close');

        $storage = new SessionHandlerInterfaceStorage($handler);
        $storage->delete('session-id');
    }

    public function testGarbageCollectDelegatesToGcWithMaxLifetime(): void
    {
        $handler = $this->createMock(SessionHandlerInterface::class);
        $handler->expects($this->once())->method('open')->with('', SwooleSessionStorage::DEFAULT_SESSION_NAME);
        $handler->expects($this->once())->method('gc')->with((int) ini_get('session.gc_maxlifetime'));
        $handler->expects($this->once())->method('close');

        $storage = new SessionHandlerInterfaceStorage($handler);
        $storage->garbageCollect();
    }

    public function testGetReturnsNullOnEmptyReadAndDoesNotCallExpired(): void
    {
        $handler = $this->createMock(SessionHandlerInterface::class);
        $handler->expects($this->once())->method('open')->with('', SwooleSessionStorage::DEFAULT_SESSION_NAME);
        $handler->expects($this->once())->method('read')->with('session-id')->willReturn('');
        $handler->expects($this->once())->method('close');

        $expiredCalled = new stdClass();
        $expiredCalled->value = false;
        $expired = static function () use ($expiredCalled): void {
            $expiredCalled->value = true;
        };

        $storage = new SessionHandlerInterfaceStorage($handler);
        $result = $storage->get('session-id', $expired);

        $this->assertNull($result);
        $this->assertFalse($expiredCalled->value);
    }

    public function testGetReturnsNullOnFalseReadAndDoesNotCallExpired(): void
    {
        $handler = $this->createMock(SessionHandlerInterface::class);
        $handler->expects($this->once())->method('open')->with('', SwooleSessionStorage::DEFAULT_SESSION_NAME);
        $handler->expects($this->once())->method('read')->with('session-id')->willReturn(false);
        $handler->expects($this->once())->method('close');

        $expiredCalled = new stdClass();
        $expiredCalled->value = false;
        $expired = static function () use ($expiredCalled): void {
            $expiredCalled->value = true;
        };

        $storage = new SessionHandlerInterfaceStorage($handler);
        $result = $storage->get('session-id', $expired);

        $this->assertNull($result);
        $this->assertFalse($expiredCalled->value);
    }

    public function testGetReturnsStringOnNonEmptyRead(): void
    {
        $handler = $this->createMock(SessionHandlerInterface::class);
        $handler->expects($this->once())->method('open')->with('', SwooleSessionStorage::DEFAULT_SESSION_NAME);
        $handler->expects($this->once())->method('read')->with('session-id')->willReturn('serialized-data');
        $handler->expects($this->once())->method('close');

        $storage = new SessionHandlerInterfaceStorage($handler);
        $result = $storage->get('session-id');

        $this->assertSame('serialized-data', $result);
    }

    public function testCountThrowsLogicException(): void
    {
        $handler = $this->createMock(SessionHandlerInterface::class);
        $handler->expects($this->never())->method('open');
        $handler->expects($this->never())->method('close');

        $storage = new SessionHandlerInterfaceStorage($handler);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Counting sessions is not supported when using a SessionHandlerInterface-backed storage.'
        );
        $storage->count();
    }
}
