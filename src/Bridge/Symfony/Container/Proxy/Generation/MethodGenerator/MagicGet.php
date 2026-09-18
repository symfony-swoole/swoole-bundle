<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator;

use InvalidArgumentException;
use Laminas\Code\Generator\ParameterGenerator;
use Laminas\Code\Generator\PropertyGenerator;
use ProxyManager\Generator\MagicMethodGenerator;
use ReflectionClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\Util\PublicScopeSimulator;

/**
 * Magic `__get` for lazy loading value holder objects.
 */
final class MagicGet extends MagicMethodGenerator
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
        string $isPublicProperty,
    ) {
        parent::__construct($originalClass, '__get', [new ParameterGenerator('name')]);

        // By value for a readonly class. ProxyManager returns a reference from __get so that indirect
        // modification reaches the real object, but every property of a readonly class is readonly, and
        // taking a reference to one counts as modifying it - reading a property through the proxy would
        // fail with "Cannot modify readonly property".
        $byReference = !$originalClass->isReadOnly();
        $this->setReturnsReference($byReference);

        $hasParent = $originalClass->hasMethod('__get');

        $servicePool = $servicePoolProperty->getName();
        $callParent = 'if (' . $isPublicProperty . ") {\n"
            . '    return $this->' . $servicePool . '->get()->$name;'
            . "\n}\n\n";

        if ($hasParent) {
            $this->setBody($callParent . 'return $this->' . $servicePool . '->get()->__get($name);');

            return;
        }

        $this->setBody(
            $callParent . PublicScopeSimulator::getPublicAccessSimulationCode(
                PublicScopeSimulator::OPERATION_GET,
                'name',
                null,
                new PropertyGenerator($servicePool . '->get()'),
                null,
                $originalClass,
                $byReference,
            )
        );
    }
}
