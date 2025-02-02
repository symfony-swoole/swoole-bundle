<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use Assert\Assertion;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\DependencyInjection\Definition;

trait ProxifierAssertions
{
    private function assertServiceIsNotReadOnly(string $serviceId, Definition $serviceDef): void
    {
        $class = $serviceDef->getClass();
        Assertion::classExists($class);
        $reflClass = new ReflectionClass($class);

        if ($reflClass->isReadOnly()) {
            throw new RuntimeException(sprintf('Unable to proxify service %s, because it is read-only', $serviceId));
        }
    }
}
