<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Reflection;

use ZEngine\Core;
use ZEngine\Reflection\ReflectionClass;
use ZEngine\Reflection\ReflectionMethod;

/**
 * Strips `final` from classes at runtime, through z-engine, so the service pool's proxies can extend them.
 *
 * The change is to the class in this process's memory and lasts as long as the process. So the compile does it
 * for the classes it proxies, and records them in the container it builds
 * (ContainerConstants::PARAM_COROUTINES_FINAL_CLASSES); every other process strips the same classes again once
 * it has loaded that container, before it instantiates anything from it.
 */
final class ClassModifier
{
    /**
     * What this process has stripped. z-engine's change outlives the kernel that asked for it, so a second
     * compile in the same process - cache:clear compiles the container it warms up after the one it booted with -
     * finds those classes no longer final, and without this would record none of them in the container it
     * builds. That container is the one that ends up in the cache, and every process loading it would strip
     * nothing.
     *
     * @var array<class-string, true>
     */
    private static array $strippedClasses = [];

    /**
     * The kernel can be booted more than once per process (cache:clear boots a temporary kernel of
     * its own), but Core::init() is not idempotent - every call builds a brand new FFI binding and
     * drops the previous one. Anything still pointing into the dropped binding, most notably the FFI
     * callbacks installed by ReflectionFunction::redefine(), is invalidated by that and takes the
     * process down with a segmentation fault the next time it is used.
     */
    public static function initialize(): void
    {
        if (isset(Core::$executor)) {
            return;
        }

        Core::init();
    }

    /**
     * @param class-string $className
     * @return bool whether the class is one this modifies - and so one every process that proxies it has to
     *              modify, whether or not this call was the one to do it here
     */
    public static function removeFinalFlagsFromClass(string $className): bool
    {
        if (isset(self::$strippedClasses[$className])) {
            return true;
        }

        $reflClass = new ReflectionClass($className);

        if (self::hasNativeParents($reflClass)) {
            // native classes should not be final and z-engine has problems with them
            return false;
        }

        $finalMethods = $reflClass->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_FINAL);

        if (!$reflClass->isFinal() && count($finalMethods) === 0) {
            return false;
        }

        $reflClass->setFinal(false);

        foreach ($finalMethods as $reflMethod) {
            $reflMethod->setFinal(false);
        }

        self::$strippedClasses[$className] = true;

        return true;
    }

    /**
     * @param iterable<class-string> $classNames
     */
    public static function removeFinalFlagsFromClasses(iterable $classNames): void
    {
        foreach ($classNames as $className) {
            self::removeFinalFlagsFromClass($className);
        }
    }

    private static function hasNativeParents(ReflectionClass $class): bool
    {
        do {
            if ($class->isInternal()) {
                return true;
            }

            $class = $class->getParentClass();
        } while ($class instanceof ReflectionClass);

        return false;
    }
}
