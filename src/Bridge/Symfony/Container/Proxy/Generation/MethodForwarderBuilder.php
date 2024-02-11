<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation;

use Laminas\Code\Reflection\MethodReflection;
use ReflectionMethod;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\ForwardedMethod;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\PropertyGenerator\ServicePoolProperty;

final class MethodForwarderBuilder
{
    public function buildMethodInterceptor(ServicePoolProperty $servicePoolHolderProperty): callable
    {
        return static fn(ReflectionMethod $method): ForwardedMethod => ForwardedMethod::generateMethod(
            new MethodReflection($method->getDeclaringClass()->getName(), $method->getName()),
            $servicePoolHolderProperty
        );
    }
}
