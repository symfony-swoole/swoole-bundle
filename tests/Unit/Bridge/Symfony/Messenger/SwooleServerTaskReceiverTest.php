<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Messenger;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\Exception\ReceiverNotAvailableException;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\SwooleServerTaskReceiver;
use Symfony\Component\Messenger\Envelope;

final class SwooleServerTaskReceiverTest extends TestCase
{
    use ProphecyTrait;

    public function testThatItThrowsExceptionOnGet(): void
    {
        $receiver = new SwooleServerTaskReceiver();

        $this->expectException(ReceiverNotAvailableException::class);

        $receiver->get();
    }

    public function testThatItThrowsExceptionOnReject(): void
    {
        $receiver = new SwooleServerTaskReceiver();

        $this->expectException(ReceiverNotAvailableException::class);

        $receiver->reject(new Envelope($this->prophesize('stdClass')->reveal()));
    }

    public function testThatItThrowsExceptionOnAck(): void
    {
        $receiver = new SwooleServerTaskReceiver();

        $this->expectException(ReceiverNotAvailableException::class);

        $receiver->ack(new Envelope($this->prophesize('stdClass')->reveal()));
    }
}
