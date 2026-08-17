<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Server\Config\Socket;
use SwooleBundle\SwooleBundle\Server\Config\Sockets;
use SwooleBundle\SwooleBundle\Server\DefaultHttpServerConfiguration;

final class DefaultHttpServerConfigurationTest extends TestCase
{
    private const string PID_FILE = '/tmp/swoole.pid';

    public function testRejectsConfiguredPidFileForForegroundServer(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Pid file can only be configured when using daemon mode.');

        $this->makeConfiguration(self::PID_FILE);
    }

    public function testPassesConfiguredPidFileToDaemonServer(): void
    {
        $configuration = $this->makeConfiguration();
        $configuration->daemonize(self::PID_FILE);

        self::assertSame(self::PID_FILE, $configuration->getSwooleSettings()['pid_file']);
    }

    private function makeConfiguration(?string $pidFile = null): DefaultHttpServerConfiguration
    {
        $swoole = $this->createStub(Swoole::class);
        $swoole->method('cpuCoresCount')->willReturn(1);
        $swoole->method('supportsRunningMode')->willReturn(true);

        return new DefaultHttpServerConfiguration(
            $swoole,
            new Sockets(new Socket()),
            settings: $pidFile === null ? [] : ['pid_file' => $pidFile],
        );
    }
}
