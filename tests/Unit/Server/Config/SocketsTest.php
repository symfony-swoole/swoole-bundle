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
