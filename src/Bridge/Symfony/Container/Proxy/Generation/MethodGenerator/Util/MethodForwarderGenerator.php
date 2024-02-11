<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\Util;

use Laminas\Code\Generator\PropertyGenerator;
use ProxyManager\Generator\Util\ProxiedMethodReturnExpression;
use ReflectionMethod;

/**
 * Utility to service pool method interceptor.
 */
final class MethodForwarderGenerator
{
    private const TEMPLATE = <<<'PHP'
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
        $replacements = [
            '{{$forwardedMethodCall}}' => $forwardedMethodCall,
            '{{$returnExpression}}' => ProxiedMethodReturnExpression::generate('$returnValue', $originalMethod),
            '{{$servicePoolHolderName}}' => $servicePoolHolderName,
        ];

        return str_replace(array_keys($replacements), $replacements, self::TEMPLATE);
    }
}
