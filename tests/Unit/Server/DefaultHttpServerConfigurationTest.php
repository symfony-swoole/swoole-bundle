<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Server\Config\Socket;
use SwooleBundle\SwooleBundle\Server\Config\Sockets;
use SwooleBundle\SwooleBundle\Server\DefaultHttpServerConfiguration;

final class DefaultHttpServerConfigurationTest extends TestCase
{
    private $swoole;
    private $sockets;

    protected function setUp(): void
    {
        $this->swoole = $this->createMock(Swoole::class);
        $this->swoole->method('cpuCoresCount')->willReturn(4);
        $this->sockets = new Sockets(new Socket('0.0.0.0', 9501));
    }

    public function testDefaultValues(): void
    {
        $config = new DefaultHttpServerConfiguration($this->swoole, $this->sockets);

        $this->assertFalse($config->hasOpenHttp2Protocol());
        $this->assertFalse($config->hasOpenTcpNodelay());
    }
}
