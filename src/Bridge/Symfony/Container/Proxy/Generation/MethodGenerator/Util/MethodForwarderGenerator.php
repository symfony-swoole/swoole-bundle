<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\Util;

use Laminas\Code\Generator\PropertyGenerator;
use ProxyManager\Generator\Util\ProxiedMethodReturnExpression;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Utility to service pool method interceptor.
 */
final class MethodForwarderGenerator
{
    private const string TEMPLATE = <<<'PHP'
                $wrapped = $this->{{$servicePoolHolderName}}->get();
                $returnValue = $wrapped->{{$forwardedMethodCall}};

                {{$returnExpression}}
        PHP;

    /**
     * @param string $forwardedMethodCall the call to the proxied method
     */
    public static function createForwardedMethodBody(
        string $forwardedMethodCall,
        PropertyGenerator $servicePoolHolder,
        ?ReflectionMethod $originalMethod,
    ): string {
        $servicePoolHolderName = $servicePoolHolder->getName();

        // Check if method returns static or self
        $returnExpression = self::generateReturnExpression($originalMethod);

        $replacements = [
            '{{$forwardedMethodCall}}' => $forwardedMethodCall,
            '{{$returnExpression}}' => $returnExpression,
            '{{$servicePoolHolderName}}' => $servicePoolHolderName,
        ];

        return str_replace(array_keys($replacements), $replacements, self::TEMPLATE);
    }

    private static function generateReturnExpression(?ReflectionMethod $originalMethod): string
    {
        if ($originalMethod === null) {
            return ProxiedMethodReturnExpression::generate('$returnValue', null);
        }

        $returnType = $originalMethod->getReturnType();

        // Check if return type is 'static' or 'self'
        if ($returnType instanceof ReflectionNamedType) {
            $typeName = $returnType->getName();

            if ($typeName === 'static') {
                // Return $this (the proxy) instead of $returnValue (the wrapped object)
                // This ensures the proxy is returned when the method returns static/self
                return 'return $this;';
            }
        }

        // Default behavior for other return types
        return ProxiedMethodReturnExpression::generate('$returnValue', $originalMethod);
    }
}
