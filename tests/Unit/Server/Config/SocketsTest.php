<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Config;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Config\Socket;
use SwooleBundle\SwooleBundle\Server\Config\Sockets;

final class SocketsTest extends TestCase
{
    private Socket $serverSocket;
    private Sockets $sockets;

    protected function setUp(): void
    {
        $this->serverSocket = new Socket('0.0.0.0', 9501);
        $this->sockets = new Sockets($this->serverSocket);
    }

    public function testGetServerSocket(): void
    {
        $this->assertSame($this->serverSocket, $this->sockets->getServerSocket());
    }

    public function testApiSocket(): void
    {
        $this->assertFalse($this->sockets->hasApiSocket());

        $apiSocket = new Socket('0.0.0.0', 9200);
        $this->sockets->changeApiSocket($apiSocket);

        $this->assertTrue($this->sockets->hasApiSocket());
        $this->assertSame($apiSocket, $this->sockets->getApiSocket());

        $this->sockets->disableApiSocket();
        $this->assertFalse($this->sockets->hasApiSocket());
    }

    public function testGrpcSocket(): void
    {
        $this->assertFalse($this->sockets->hasGrpcSocket());

        $grpcSocket = new Socket('0.0.0.0', 9503);
        $this->sockets->changeGrpcSocket($grpcSocket);

        $this->assertTrue($this->sockets->hasGrpcSocket());
        $this->assertSame($grpcSocket, $this->sockets->getGrpcSocket());

        $this->sockets->disableGrpcSocket();
        $this->assertFalse($this->sockets->hasGrpcSocket());
    }

    public function testGetAll(): void
    {
        $apiSocket = new Socket('0.0.0.0', 9200);
        $grpcSocket = new Socket('0.0.0.0', 9503);
        $additionalSocket = new Socket('0.0.0.0', 9505);

        $sockets = new Sockets($this->serverSocket, $apiSocket, $grpcSocket, $additionalSocket);

        $all = iterator_to_array($sockets->getAll(), false);

        $this->assertCount(4, $all);
        $this->assertSame($this->serverSocket, $all[0]);
        $this->assertSame($apiSocket, $all[1]);
        $this->assertSame($grpcSocket, $all[2]);
        $this->assertSame($additionalSocket, $all[3]);
    }
}
