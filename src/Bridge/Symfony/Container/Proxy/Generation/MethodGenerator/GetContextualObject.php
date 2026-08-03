<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator;

use Laminas\Code\Generator\Exception\InvalidArgumentException;
use ProxyManager\Generator\MethodGenerator;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\PropertyGenerator\ServicePoolProperty;

/**
 * Implementation for `getContextualObject()` of
 * {@see \SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy}.
 *
 * Unwraps the proxy: hands out the very instance every forwarded method call would run on, which is
 * the instance assigned to the running coroutine.
 */
final class GetContextualObject extends MethodGenerator
{
    /**
     * Constructor.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(ServicePoolProperty $servicePoolHolderProperty)
    {
        parent::__construct('getContextualObject');

        $this->setBody('return $this->' . $servicePoolHolderProperty->getName() . '->get();');
        $this->setReturnType('object');
    }
}
