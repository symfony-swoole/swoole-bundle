<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator;

use Laminas\Code\Generator\ParameterGenerator;
use Laminas\Code\Generator\PropertyGenerator;
use ProxyManager\Generator\MagicMethodGenerator;
use ProxyManager\ProxyGenerator\PropertyGenerator\PublicPropertiesMap;
use ProxyManager\ProxyGenerator\Util\PublicScopeSimulator;

/**
 * Magic `__set` for lazy loading value holder objects.
 */
class MagicSet extends MagicMethodGenerator
{
    /**
     * Constructor.
     *
     * @template T of object
     *
     * @param \ReflectionClass<T> $originalClass
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        \ReflectionClass $originalClass,
        PropertyGenerator $servicePoolProperty,
        PublicPropertiesMap $publicProperties
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
            $callParent = 'if (isset(self::$'.$publicProperties->getName()."[\$name])) {\n"
                .'    return ($this->'.$servicePool.'->get()->$name = $value);'
                ."\n}\n\n";
        }

        $callParent .= $hasParent
            ? 'return $this->'.$servicePool.'->get()->__set($name, $value);'
            : PublicScopeSimulator::getPublicAccessSimulationCode(
                PublicScopeSimulator::OPERATION_SET,
                'name',
                'value',
                new PropertyGenerator($servicePool.'->get()'),
                null,
                $originalClass
            );

        $this->setBody($callParent);
    }
}
