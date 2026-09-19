<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation;

use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\MethodGenerator;
use Laminas\Code\Generator\PropertyGenerator;
use Laminas\Code\Generator\TypeGenerator;
use Laminas\Code\Reflection\MethodReflection;
use Override;
use ProxyManager\Generator\Util\ClassGeneratorUtils;
use ProxyManager\Generator\Util\IdentifierSuffixer;
use ProxyManager\Proxy\AccessInterceptorValueHolderInterface;
use ProxyManager\ProxyGenerator\AccessInterceptor\MethodGenerator\MagicWakeup;
use ProxyManager\ProxyGenerator\AccessInterceptor\MethodGenerator\SetMethodPrefixInterceptor;
use ProxyManager\ProxyGenerator\AccessInterceptor\MethodGenerator\SetMethodSuffixInterceptor;
use ProxyManager\ProxyGenerator\AccessInterceptorValueHolder\MethodGenerator\InterceptedMethod;
use ProxyManager\ProxyGenerator\Assertion\CanProxyAssertion;
use ProxyManager\ProxyGenerator\PropertyGenerator\PublicPropertiesMap;
use ProxyManager\ProxyGenerator\ProxyGeneratorInterface;
use ProxyManager\ProxyGenerator\Util\Properties;
use ProxyManager\ProxyGenerator\Util\ProxiedMethodsFilter;
use ProxyManager\ProxyGenerator\ValueHolder\MethodGenerator\GetWrappedValueHolderValue;
use ProxyManager\ProxyGenerator\ValueHolder\MethodGenerator\MagicSleep;
use ReflectionClass;
use ReflectionMethod;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\AccessInterceptorState;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\AccessInterceptor\Constructor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\AccessInterceptor\MagicClone;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\AccessInterceptor\MagicPropertyAccess;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\AccessInterceptor\StaticProxyConstructor;

/**
 * ProxyManager's access interceptor value holder, for a class that is readonly.
 *
 * A readonly class can only be extended by a readonly class, and ProxyManager's own generator can make
 * neither kind of proxy it would need: it never marks the class readonly, and its proxy changes its own
 * properties after it is built - the interceptors are set on it one method at a time, through the
 * setters its interface requires. So a readonly one keeps a single readonly property holding an
 * {@see AccessInterceptorState}, and everything that changes later changes inside that object.
 *
 * Most of the proxy is still ProxyManager's. Its method generators only ever use a property's name, to
 * write `$this->name` into the code they produce, so they are handed names that reach into the state
 * object - `state->valueHolder`, `state->prefixInterceptors` - and generate exactly what they always do:
 * the intercepted methods, getWrappedValueHolderValue() and both interceptor setters. What has to differ
 * is generated here: the two constructors, which create the state rather than writing properties;
 * __clone, which gives the copy a state of its own; and the property magic, whose map of public
 * properties is a constant and which reads by value.
 *
 * Every class that is not readonly is still proxied by ProxyManager's own generator - see
 * {@see ReadonlyAwareAccessInterceptorGenerator}.
 */
final readonly class ReadonlyAccessInterceptorGenerator implements ProxyGeneratorInterface
{
    /**
     * @template T of object
     * @param ReflectionClass<T> $originalClass
     */
    #[Override]
    public function generate(ReflectionClass $originalClass, ClassGenerator $classGenerator): void
    {
        CanProxyAssertion::assertClassCanBeProxied($originalClass);

        $interfaces = [AccessInterceptorValueHolderInterface::class];

        if ($originalClass->isInterface()) {
            $interfaces[] = $originalClass->getName();
        } else {
            $classGenerator->setExtendedClass($originalClass->getName());
        }

        $classGenerator->setReadonly(true);
        $classGenerator->setImplementedInterfaces($interfaces);
        $classGenerator->addPropertyFromGenerator($state = self::stateProperty());

        $isPublicProperty = PublicPropertiesLookup::add(
            $classGenerator,
            new PublicPropertiesMap(Properties::fromReflectionClass($originalClass)),
        );
        $valueHolder = self::inState($state, 'valueHolder');
        $prefixInterceptors = self::inState($state, 'prefixInterceptors');
        $suffixInterceptors = self::inState($state, 'suffixInterceptors');

        array_map(
            static function (MethodGenerator $generatedMethod) use ($originalClass, $classGenerator): void {
                ClassGeneratorUtils::addMethodIfNotFinal($originalClass, $classGenerator, $generatedMethod);
            },
            array_merge(
                array_map(
                    static fn(ReflectionMethod $method): InterceptedMethod => InterceptedMethod::generateMethod(
                        new MethodReflection($method->getDeclaringClass()->getName(), $method->getName()),
                        $valueHolder,
                        $prefixInterceptors,
                        $suffixInterceptors,
                    ),
                    ProxiedMethodsFilter::getProxiedMethods($originalClass),
                ),
                [
                    Constructor::generateMethod($originalClass, $state),
                    new StaticProxyConstructor($originalClass, $state),
                    new GetWrappedValueHolderValue($valueHolder),
                    new SetMethodPrefixInterceptor($prefixInterceptors),
                    new SetMethodSuffixInterceptor($suffixInterceptors),
                    new MagicPropertyAccess(
                        $originalClass,
                        '__get',
                        $valueHolder,
                        $prefixInterceptors,
                        $suffixInterceptors,
                        $isPublicProperty,
                    ),
                    new MagicPropertyAccess(
                        $originalClass,
                        '__set',
                        $valueHolder,
                        $prefixInterceptors,
                        $suffixInterceptors,
                        $isPublicProperty,
                    ),
                    new MagicPropertyAccess(
                        $originalClass,
                        '__isset',
                        $valueHolder,
                        $prefixInterceptors,
                        $suffixInterceptors,
                        $isPublicProperty,
                    ),
                    new MagicPropertyAccess(
                        $originalClass,
                        '__unset',
                        $valueHolder,
                        $prefixInterceptors,
                        $suffixInterceptors,
                        $isPublicProperty,
                    ),
                    new MagicClone($originalClass, $state),
                    new MagicSleep($originalClass, $state),
                    new MagicWakeup($originalClass),
                ],
            ),
        );
    }

    /**
     * The proxy's one property. Typed and without a default, as a readonly property has to be.
     */
    private static function stateProperty(): PropertyGenerator
    {
        $state = new PropertyGenerator(IdentifierSuffixer::getIdentifier('interceptorState'));
        $state->setVisibility(PropertyGenerator::VISIBILITY_PRIVATE);
        $state->setType(TypeGenerator::fromTypeString(AccessInterceptorState::class));
        $state->omitDefaultValue();

        return $state;
    }

    /**
     * A member of the state object, named so that ProxyManager's generators write their `$this->name`
     * straight into it. Never added to the class: only its name is ever used.
     */
    private static function inState(PropertyGenerator $state, string $member): PropertyGenerator
    {
        return new PropertyGenerator($state->getName() . '->' . $member);
    }
}
