<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator;

use InvalidArgumentException;
use Laminas\Code\Generator\ParameterGenerator;
use Laminas\Code\Generator\PropertyGenerator;
use ProxyManager\Generator\MagicMethodGenerator;
use ProxyManager\ProxyGenerator\PropertyGenerator\PublicPropertiesMap;
use ProxyManager\ProxyGenerator\Util\PublicScopeSimulator;
use ReflectionClass;

/**
 * Magic `__set` for lazy loading value holder objects.
 */
final class MagicSet extends MagicMethodGenerator
{
    /**
     * Constructor.
     *
     * @template T of object
     * @param ReflectionClass<T> $originalClass
     * @param string $isPublicProperty the condition that tells a public property of the parent by its $name
     * @throws InvalidArgumentException
     */
    public function __construct(
        ReflectionClass $originalClass,
        PropertyGenerator $servicePoolProperty,
        PublicPropertiesMap $publicProperties,
        string $isPublicProperty,
    ) {
        parent::__construct(
            $originalClass,
            '__set',
            [new ParameterGenerator('name'), new ParameterGenerator('value')]
        );

        $hasParent = $originalClass->hasMethod('__set');
        $servicePool = $servicePoolProperty->getName();
        $callParent = '';

        if (!$publicProperties->isEmpty()) {
            $callParent = 'if (' . $isPublicProperty . ") {\n"
                . '    return ($this->' . $servicePool . '->get()->$name = $value);'
                . "\n}\n\n";
        }

        $callParent .= $hasParent
            ? 'return $this->' . $servicePool . '->get()->__set($name, $value);'
            : PublicScopeSimulator::getPublicAccessSimulationCode(
                PublicScopeSimulator::OPERATION_SET,
                'name',
                'value',
                new PropertyGenerator($servicePool . '->get()'),
                null,
                $originalClass
            );

        $this->setBody($callParent);
    }
}
