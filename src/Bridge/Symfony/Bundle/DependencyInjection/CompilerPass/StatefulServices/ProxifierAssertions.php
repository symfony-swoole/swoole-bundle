<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use Assert\Assertion;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\DependencyInjection\Definition;

trait ProxifierAssertions
{
    private function assertServiceIsNotReadOnly(string $serviceId, Definition $serviceDef): void
    {
        $class = $serviceDef->getClass();
        Assertion::string($class);

        if (interface_exists($class)) {
            trigger_error(
                sprintf(
                    'Definition class \'%s\' is an interface, swoole bundle cannot guarantee proper functioning '
                        . 'with such a definition type.'
                        . (
                            $class === AdapterInterface::class
                            ? ' Try using e.g. \'system\': \'cache.adapter.filesystem\' directly as cache.'
                            : ''
                        ),
                    $class,
                ),
                E_USER_WARNING,
            );

            return;
        }

        Assertion::classExists($class);
        $reflClass = new ReflectionClass($class);

        if ($reflClass->isReadOnly()) {
            throw new RuntimeException(sprintf('Unable to proxify service %s, because it is read-only', $serviceId));
        }
    }
}
