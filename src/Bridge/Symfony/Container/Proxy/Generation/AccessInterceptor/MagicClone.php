<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\AccessInterceptor;

use Laminas\Code\Generator\PropertyGenerator;
use ProxyManager\Generator\MagicMethodGenerator;
use ReflectionClass;

/**
 * `__clone` of a readonly access interceptor proxy.
 *
 * ProxyManager's clones the wrapped object and every interceptor into the copy's own properties. Here
 * those live in the state object, which a plain clone would leave shared between the original and the
 * copy - so the copy is given a state of its own first, which PHP allows because __clone may write a
 * readonly property once, and the rest is cloned into that.
 */
final class MagicClone extends MagicMethodGenerator
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $originalClass
     */
    public function __construct(ReflectionClass $originalClass, PropertyGenerator $state)
    {
        parent::__construct($originalClass, '__clone');

        $stateOfThis = '$this->' . $state->getName();

        $this->setBody(
            $stateOfThis . ' = clone ' . $stateOfThis . ";\n"
            . $stateOfThis . '->valueHolder = clone ' . $stateOfThis . "->valueHolder;\n\n"
            . 'foreach (' . $stateOfThis . '->prefixInterceptors as $key => $value) {' . "\n"
            . '    ' . $stateOfThis . '->prefixInterceptors[$key] = clone $value;' . "\n"
            . "}\n\n"
            . 'foreach (' . $stateOfThis . '->suffixInterceptors as $key => $value) {' . "\n"
            . '    ' . $stateOfThis . '->suffixInterceptors[$key] = clone $value;' . "\n"
            . '}'
        );
    }
}
