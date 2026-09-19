<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\AccessInterceptor;

use Laminas\Code\Generator\PropertyGenerator;
use Laminas\Code\Reflection\MethodReflection;
use Laminas\Code\Reflection\ParameterReflection;
use ProxyManager\Generator\MethodGenerator;
use ProxyManager\ProxyGenerator\Util\Properties;
use ProxyManager\ProxyGenerator\Util\UnsetPropertiesGenerator;
use ReflectionClass;
use ReflectionMethod;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\AccessInterceptorState;

/**
 * The `__construct()` of a readonly access interceptor proxy, for a proxy built with `new` rather than
 * through staticProxyConstructor() - ProxyManager's, with the wrapped object created into the state
 * object instead of into a property of its own.
 *
 * `isset()` is what tells a first call from a later one: the state property is readonly, and reading one
 * that was never written fails where isset() answers false.
 */
final class Constructor extends MethodGenerator
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $originalClass
     */
    public static function generateMethod(ReflectionClass $originalClass, PropertyGenerator $state): MethodGenerator
    {
        $originalConstructor = self::originalConstructor($originalClass);
        $constructor = $originalConstructor !== null
            ? self::fromReflectionWithoutBodyAndDocBlock($originalConstructor)
            : new self('__construct');
        $stateName = $state->getName();

        $constructor->setBody(
            'static $reflection;' . "\n\n"
            . 'if (! isset($this->' . $stateName . ')) {' . "\n"
            . '    $reflection = $reflection ?? new \ReflectionClass(' . var_export($originalClass->getName(), true)
            . ");\n"
            . '    $this->' . $stateName . ' = new \\' . AccessInterceptorState::class
            . '($reflection->newInstanceWithoutConstructor());' . "\n"
            . UnsetPropertiesGenerator::generateSnippet(Properties::fromReflectionClass($originalClass), 'this')
            . '}'
            . ($originalConstructor !== null ? self::originalConstructorCall($originalConstructor, $stateName) : '')
        );

        return $constructor;
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $class
     */
    private static function originalConstructor(ReflectionClass $class): ?MethodReflection
    {
        foreach ($class->getMethods() as $method) {
            if ($method->isConstructor()) {
                return self::reflectionOf($method);
            }
        }

        return null;
    }

    private static function reflectionOf(ReflectionMethod $method): MethodReflection
    {
        return new MethodReflection($method->getDeclaringClass()->getName(), $method->getName());
    }

    private static function originalConstructorCall(MethodReflection $originalConstructor, string $stateName): string
    {
        return "\n\n"
            . '$this->' . $stateName . '->valueHolder->' . $originalConstructor->getName() . '('
            . implode(', ', array_map(
                static fn(ParameterReflection $parameter): string => ($parameter->isVariadic() ? '...' : '')
                    . '$' . $parameter->getName(),
                $originalConstructor->getParameters(),
            ))
            . ');';
    }
}
