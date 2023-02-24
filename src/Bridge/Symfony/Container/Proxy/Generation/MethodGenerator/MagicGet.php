<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator;

use Laminas\Code\Generator\ParameterGenerator;
use Laminas\Code\Generator\PropertyGenerator;
use ProxyManager\Generator\MagicMethodGenerator;
use ProxyManager\ProxyGenerator\PropertyGenerator\PublicPropertiesMap;
use ProxyManager\ProxyGenerator\Util\PublicScopeSimulator;

/**
 * Magic `__get` for lazy loading value holder objects.
 */
class MagicGet extends MagicMethodGenerator
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
        parent::__construct($originalClass, '__get', [new ParameterGenerator('name')]);

        $hasParent = $originalClass->hasMethod('__get');

        $servicePool = $servicePoolProperty->getName();
        $callParent = 'if (isset(self::$'.$publicProperties->getName()."[\$name])) {\n"
            .'    return $this->'.$servicePool.'->get()->$name;'
            ."\n}\n\n";

        if ($hasParent) {
            $this->setBody(
                $callParent.'return $this->'.$servicePool.'->get()->__get($name);'
            );

            return;
        }

        $this->setBody(
            $callParent.PublicScopeSimulator::getPublicAccessSimulationCode(
                PublicScopeSimulator::OPERATION_GET,
                'name',
                null,
                new PropertyGenerator($servicePool.'->get()'),
                null,
                $originalClass
            )
        );
    }
}
