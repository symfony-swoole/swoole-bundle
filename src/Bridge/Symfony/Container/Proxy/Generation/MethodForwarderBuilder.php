<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container\Proxy\Generation;

use K911\Swoole\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\ForwardedMethod;
use K911\Swoole\Bridge\Symfony\Container\Proxy\Generation\PropertyGenerator\ServicePoolProperty;
use Laminas\Code\Reflection\MethodReflection;

final class MethodForwarderBuilder
{
    public function buildMethodInterceptor(ServicePoolProperty $servicePoolHolderProperty): callable
    {
        return static function (\ReflectionMethod $method) use ($servicePoolHolderProperty): ForwardedMethod {
            return ForwardedMethod::generateMethod(
                new MethodReflection($method->getDeclaringClass()->getName(), $method->getName()),
                $servicePoolHolderProperty
            );
        };
    }
}
