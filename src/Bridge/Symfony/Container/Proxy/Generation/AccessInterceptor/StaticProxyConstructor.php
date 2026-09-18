<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\AccessInterceptor;

use Laminas\Code\Generator\Exception\InvalidArgumentException;
use Laminas\Code\Generator\ParameterGenerator;
use Laminas\Code\Generator\PropertyGenerator;
use ProxyManager\Generator\MethodGenerator;
use ProxyManager\ProxyGenerator\Util\Properties;
use ProxyManager\ProxyGenerator\Util\UnsetPropertiesGenerator;
use ReflectionClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\AccessInterceptorState;

/**
 * `staticProxyConstructor()` of a readonly access interceptor proxy - ProxyManager's, with the three
 * properties it would write gathered into the one state object the proxy can hold.
 */
final class StaticProxyConstructor extends MethodGenerator
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $originalClass
     * @throws InvalidArgumentException
     */
    public function __construct(ReflectionClass $originalClass, PropertyGenerator $state)
    {
        parent::__construct('staticProxyConstructor', [], self::FLAG_PUBLIC | self::FLAG_STATIC);

        $prefix = new ParameterGenerator('prefixInterceptors');
        $suffix = new ParameterGenerator('suffixInterceptors');

        $prefix->setDefaultValue([]);
        $suffix->setDefaultValue([]);
        $prefix->setType('array');
        $suffix->setType('array');

        $this->setParameter(new ParameterGenerator('wrappedObject'));
        $this->setParameter($prefix);
        $this->setParameter($suffix);
        $this->setReturnType($originalClass->getName());

        $this->setDocBlock(
            "Constructor to setup interceptors\n\n"
            . '@param \\' . $originalClass->getName() . " \$wrappedObject\n"
            . "@param \\Closure[] \$prefixInterceptors method interceptors to be used before method logic\n"
            . "@param \\Closure[] \$suffixInterceptors method interceptors to be used before method logic\n\n"
            . '@return self'
        );

        $this->setBody(
            'static $reflection;' . "\n\n"
            . '$reflection = $reflection ?? new \ReflectionClass(__CLASS__);' . "\n"
            . '$instance   = $reflection->newInstanceWithoutConstructor();' . "\n\n"
            . UnsetPropertiesGenerator::generateSnippet(Properties::fromReflectionClass($originalClass), 'instance')
            . '$instance->' . $state->getName() . ' = new \\' . AccessInterceptorState::class
            . '($wrappedObject, $prefixInterceptors, $suffixInterceptors);' . "\n\n"
            . 'return $instance;'
        );
    }
}
