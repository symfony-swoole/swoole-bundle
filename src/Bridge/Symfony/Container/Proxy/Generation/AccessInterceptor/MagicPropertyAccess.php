<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\AccessInterceptor;

use InvalidArgumentException;
use Laminas\Code\Generator\ParameterGenerator;
use Laminas\Code\Generator\PropertyGenerator;
use ProxyManager\Generator\MagicMethodGenerator;
use ProxyManager\ProxyGenerator\AccessInterceptorValueHolder\MethodGenerator\Util\InterceptorGenerator;
use ProxyManager\ProxyGenerator\Util\GetMethodIfExists;
use ReflectionClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\Util\PublicScopeSimulator;

/**
 * `__get`, `__set`, `__isset` and `__unset` of a readonly access interceptor proxy.
 *
 * The four of ProxyManager's, differing in the two ways a readonly class needs: the map of public
 * properties is a constant, asked through the condition passed in, and nothing takes a reference -
 * every property of a readonly class is readonly, and taking a reference to one counts as modifying it,
 * so a by-reference `__get` would fail on a plain read. The interceptors wrap each of them exactly as
 * ProxyManager's own do.
 */
final class MagicPropertyAccess extends MagicMethodGenerator
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $originalClass
     * @param '__get'|'__isset'|'__set'|'__unset' $methodName
     * @param string $isPublicProperty the condition that tells a public property of the parent by its $name
     * @throws InvalidArgumentException
     */
    public function __construct(
        ReflectionClass $originalClass,
        string $methodName,
        PropertyGenerator $valueHolder,
        PropertyGenerator $prefixInterceptors,
        PropertyGenerator $suffixInterceptors,
        string $isPublicProperty,
    ) {
        $parameters = [new ParameterGenerator('name')];

        if ($methodName === '__set') {
            $parameters[] = new ParameterGenerator('value');
        }

        parent::__construct($originalClass, $methodName, $parameters);

        $this->setReturnsReference(false);

        $target = '$this->' . $valueHolder->getName() . '->$name';
        [$publicAccess, $operation, $valueParameter] = match ($methodName) {
            '__get' => ['$returnValue = ' . $target . ';', PublicScopeSimulator::OPERATION_GET, null],
            '__set' => ['$returnValue = (' . $target . ' = $value);', PublicScopeSimulator::OPERATION_SET, 'value'],
            '__isset' => ['$returnValue = isset(' . $target . ');', PublicScopeSimulator::OPERATION_ISSET, null],
            '__unset' => ['unset(' . $target . ');', PublicScopeSimulator::OPERATION_UNSET, null],
        };

        $body = 'if (' . $isPublicProperty . ") {\n    " . $publicAccess . "\n} else {\n    "
            . PublicScopeSimulator::getPublicAccessSimulationCode(
                $operation,
                'name',
                $valueParameter,
                $valueHolder,
                'returnValue',
                $originalClass,
                false,
            )
            . "\n}\n\n";

        if ($methodName === '__unset') {
            $body .= '$returnValue = false;';
        }

        $this->setBody(InterceptorGenerator::createInterceptedMethodBody(
            $body,
            $this,
            $valueHolder,
            $prefixInterceptors,
            $suffixInterceptors,
            GetMethodIfExists::get($originalClass, $methodName),
        ));
    }
}
