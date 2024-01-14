<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Messenger;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\Exception\ReceiverNotAvailableException;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\SwooleServerTaskReceiver;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\SwooleServerTaskSender;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\SwooleServerTaskTransport;
use SwooleBundle\SwooleBundle\Server\Config\Socket;
use SwooleBundle\SwooleBundle\Server\Config\Sockets;
use SwooleBundle\SwooleBundle\Server\HttpServer;
use SwooleBundle\SwooleBundle\Server\HttpServerConfiguration;
use SwooleBundle\SwooleBundle\Tests\Helper\SwooleFactory;
use Symfony\Component\Messenger\Envelope;

class SwooleServerTaskTransportTest extends TestCase
{
    use \Prophecy\PhpUnit\ProphecyTrait;

    public function testThatItThrowsExceptionOnAck(): void
    {
        $transport = new SwooleServerTaskTransport(new SwooleServerTaskReceiver(), new SwooleServerTaskSender($this->makeHttpServerDummy()));

        $this->expectException(ReceiverNotAvailableException::class);

        $transport->ack(new Envelope($this->prophesize('stdClass')));
    }

    public function testThatItThrowsExceptionOnReject(): void
    {
        $transport = new SwooleServerTaskTransport(new SwooleServerTaskReceiver(), new SwooleServerTaskSender($this->makeHttpServerDummy()));

        $this->expectException(ReceiverNotAvailableException::class);

        $transport->reject(new Envelope($this->prophesize('stdClass')));
    }

    private function makeHttpServerDummy(): HttpServer
    {
        return new HttpServer(new HttpServerConfiguration(SwooleFactory::newInstance(), new Sockets(new Socket())));
    }
}
