<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server;

use Assert\Assertion;
use Swoole\Http\Server;
use Swoole\Server\Port;
use SwooleBundle\SwooleBundle\Server\Config\Socket;

final class HttpServerFactory
{
    private const SWOOLE_RUNNING_MODE = [
        'process' => SWOOLE_PROCESS,
        'reactor' => SWOOLE_BASE,
        //        'thread' => SWOOLE_THREAD,
    ];

    /**
     * @see https://github.com/swoole/swoole-docs/blob/master/modules/swoole-server/methods/construct.md#parameter
     * @see https://github.com/swoole/swoole-docs/blob/master/modules/swoole-server/methods/addListener.md#prototype
     */
    public static function make(Socket $main, string $runningMode = 'process', Socket ...$additional): Server
    {
        Assertion::inArray($runningMode, array_keys(self::SWOOLE_RUNNING_MODE));
        $mainServer = new Server($main->host(), $main->port(), self::SWOOLE_RUNNING_MODE[$runningMode], $main->type());

        $usedPorts = [$main->port() => true];
        foreach ($additional as $socket) {
            Assertion::keyNotExists(
                $usedPorts,
                $socket->port(),
                'Socket with port %s is already used. Ports cannot be duplicated.'
            );

            $additionalServer = $mainServer->addListener($socket->host(), $socket->port(), $socket->type());
            Assertion::isInstanceOf($additionalServer, Port::class);
            $usedPorts[$additionalServer->port] = true;
        }

        return $mainServer;
    }
}
