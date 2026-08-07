<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use Assert\Assertion;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\DependencyInjection\Definition;

trait ProxifierAssertions
{
    /**
     * Whether the definition names only the interface the service satisfies and leaves the class to a
     * factory - the shape `cache.system` has, where AbstractAdapter::createSystemCache() returns a
     * PhpFilesAdapter, or a chain of one with an ApcuAdapter, depending on what the machine offers. The
     * class is not knowable while the container is being compiled.
     *
     * A proxy can only forward what it can see. Built from an interface it would carry that interface's
     * methods and nothing else, so everything the real adapter adds - prune(), reset(), setLogger() -
     * would be missing from the object the container hands out. Such a service is left alone rather than
     * replaced by one that answers to less than it did.
     */
    private function isDefinedByInterfaceOnly(Definition $serviceDef): bool
    {
        $class = $serviceDef->getClass();

        return $class !== null && interface_exists($class);
    }

    private function assertServiceIsNotReadOnly(string $serviceId, Definition $serviceDef): void
    {
        $class = $serviceDef->getClass();
        Assertion::string($class);
        Assertion::classExists($class);
        $reflClass = new ReflectionClass($class);

        if ($reflClass->isReadOnly()) {
            throw new RuntimeException(sprintf('Unable to proxify service %s, because it is read-only', $serviceId));
        }
    }
}
