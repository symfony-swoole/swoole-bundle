<?php

declare(strict_types=1);

namespace K911\Swoole\Server\Middleware;

use ReflectionException;
use ReflectionProperty;
use Swoole\Http\Server;
use Swoole\Server\Port;
use UnexpectedValueException;

final class MiddlewareInjector
{
    public function injectMiddlevare(Server $server, MiddlewareFactory $factory, string $eventName = 'request'): void
    {
        $middleware = $this->getCallback($server, $eventName)
            ?: $this->getCallback($this->getPrimaryPort($server), $eventName);

        if (!is_callable($middleware)) {
            throw new UnexpectedValueException('Server middleware has not been detected.');
        }

        $server->on($eventName, $factory->createMiddleware($middleware));
    }

    /**
     * Retrieve the primary port listened by the server.
     *
     * @throws UnexpectedValueException
     */
    public function getPrimaryPort(Server $server): Port
    {
        foreach ((array) $server->ports as $port) {
            if ($port->host === $server->host && $port->port === $server->port) {
                return $port;
            }
        }

        throw new UnexpectedValueException('Primary server port was not found.');
    }

    /**
     * Retrieve a callback subscribed to a given event.
     *
     * @param Port|Server $observer
     */
    private function getCallback(object $observer, string $eventName): ?callable
    {
        try {
            $propertyName = 'on'.ucfirst($eventName);
            $property = new ReflectionProperty($observer, $propertyName);
            $property->setAccessible(true);

            return $property->getValue($observer);
        } catch (ReflectionException $e) {
            return null;
        }
    }
}
