<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Middleware;

use Swoole\Http\Server;
use Swoole\Server\Port;

final class MiddlewareInjector
{
    public function injectMiddlevare(Server $server, MiddlewareFactory $factory, string $eventName = 'request'): void
    {
        $middleware = $this->getCallback($server, $eventName)
            ?: $this->getCallback($this->getPrimaryPort($server), $eventName);

        if (!is_callable($middleware)) {
            throw new \UnexpectedValueException('Server middleware has not been detected.');
        }

        $server->on($eventName, $factory->createMiddleware($middleware));
    }

    /**
     * Retrieve the primary port listened by the server.
     *
     * @throws \UnexpectedValueException
     */
    public function getPrimaryPort(Server $server): Port
    {
        foreach ((array) $server->ports as $port) {
            if ($port->host === $server->host && $port->port === $server->port) {
                return $port;
            }
        }

        throw new \UnexpectedValueException('Primary server port was not found.');
    }

    /**
     * Retrieve a callback subscribed to a given event.
     */
    private function getCallback(Port|Server $observer, string $eventName): ?callable
    {
        try {
            $propertyName = 'on'.ucfirst($eventName);
            $property = new \ReflectionProperty($observer, $propertyName);
            $property->setAccessible(true);

            return $property->getValue($observer);
        } catch (\ReflectionException) {
            return null;
        }
    }
}
