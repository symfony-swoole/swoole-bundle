<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Bridge\Symfony\HttpFoundation\Session;

use K911\Swoole\Bridge\Symfony\Event\SessionResetEvent;
use K911\Swoole\Bridge\Symfony\HttpFoundation\Session\SwooleSessionStorage;
use K911\Swoole\Bridge\Symfony\HttpFoundation\Session\SwooleSessionStorageFactory;
use K911\Swoole\Server\Session\StorageInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

final class SwooleSessionStorageFactoryTest extends TestCase
{
    use ProphecyTrait;

    public function testCreateStorageCreatesSwooleSessionStorageInInitialState(): void
    {
        $subject = new SwooleSessionStorageFactory(
            $this->prophesize(StorageInterface::class)->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal(),
        );

        $result = $subject->createStorage(new Request());

        $this->assertInstanceOf(
            SwooleSessionStorage::class,
            $result
        );
        $this->assertFalse($result->isStarted());
        $this->assertSame(
            '',
            $result->getId()
        );
    }

    public function testCreateStorageAddsListenerForSwooleSessionResetEvent(): void
    {
        $dispatcher = $this->prophesize(EventDispatcherInterface::class);

        $dispatcher->addListener()
            ->withArguments([SessionResetEvent::NAME, Argument::type('closure')])
            ->shouldBeCalled()
        ;

        $subject = new SwooleSessionStorageFactory(
            $this->prophesize(StorageInterface::class)->reveal(),
            $dispatcher->reveal(),
        );

        $subject->createStorage(new Request());
    }
}
