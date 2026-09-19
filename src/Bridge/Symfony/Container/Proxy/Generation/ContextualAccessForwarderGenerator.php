<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation;

use InvalidArgumentException;
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\MethodGenerator;
use ProxyManager\Exception\InvalidProxiedClassException;
use ProxyManager\Generator\Util\ClassGeneratorUtils;
use ProxyManager\ProxyGenerator\Assertion\CanProxyAssertion;
use ProxyManager\ProxyGenerator\PropertyGenerator\PublicPropertiesMap;
use ProxyManager\ProxyGenerator\ProxyGeneratorInterface;
use ProxyManager\ProxyGenerator\Util\Properties;
use ProxyManager\ProxyGenerator\Util\ProxiedMethodsFilter;
use ReflectionClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\GetContextualObject;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\GetWrappedServicePoolValue;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\MagicClone;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\MagicGet;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\MagicSet;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\StaticProxyConstructor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\Unserialize;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\PropertyGenerator\ServicePoolProperty;

/**
 * Generator for proxies with service pool.
 *
 * A readonly class can only be extended by a readonly class, so its proxy is generated readonly too.
 * That is possible because the proxy's one piece of state - the service pool - is written once, when
 * the proxy is built, and the only other writes to it happen in __clone and __unserialize, where PHP
 * allows a readonly property to be initialized. What a readonly class cannot have is a static property,
 * so the public properties map becomes a constant, and the signature too, through
 * {@see \SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Signature\ReadonlyAwareClassSignatureGenerator}.
 *
 * The parent's readonly properties are unset from the class that declares them, which is the one scope
 * PHP lets unset one - ProxyManager's own snippet already treats readonly properties that way.
 */
final readonly class ContextualAccessForwarderGenerator implements ProxyGeneratorInterface
{
    /**
     * @var array<string>
     */
    private array $excludedMethods;

    public function __construct(
        private MethodForwarderBuilder $forwarderBuilder,
    ) {
        $this->excludedMethods = array_merge(ProxiedMethodsFilter::DEFAULT_EXCLUDED, ['__unserialize']);
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $originalClass
     * @throws InvalidArgumentException
     * @throws InvalidProxiedClassException
     */
    public function generate(ReflectionClass $originalClass, ClassGenerator $classGenerator): void
    {
        CanProxyAssertion::assertClassCanBeProxied($originalClass);

        $interfaces = [
            ContextualProxy::class,
        ];

        if ($originalClass->isInterface()) {
            $interfaces[] = $originalClass->getName();
        }

        if (!$originalClass->isInterface()) {
            $classGenerator->setExtendedClass($originalClass->getName());
        }

        $isReadonly = $originalClass->isReadOnly();
        $classGenerator->setReadonly($isReadonly);

        $publicProperties = new PublicPropertiesMap(Properties::fromReflectionClass($originalClass));
        $classGenerator->setImplementedInterfaces($interfaces);
        $classGenerator->addPropertyFromGenerator($servicePoolProperty = new ServicePoolProperty());
        $isPublicProperty = PublicPropertiesLookup::add($classGenerator, $publicProperties);
        $closure = static function (MethodGenerator $generatedMethod) use ($originalClass, $classGenerator): void {
            ClassGeneratorUtils::addMethodIfNotFinal($originalClass, $classGenerator, $generatedMethod);
        };

        array_map(
            $closure,
            array_merge(
                array_map(
                    $this->forwarderBuilder->buildMethodInterceptor($servicePoolProperty),
                    ProxiedMethodsFilter::getProxiedMethods($originalClass, $this->excludedMethods),
                ),
                [
                    new StaticProxyConstructor($servicePoolProperty, Properties::fromReflectionClass($originalClass)),
                    new GetWrappedServicePoolValue($servicePoolProperty),
                    new GetContextualObject($servicePoolProperty),
                    new MagicGet($originalClass, $servicePoolProperty, $isPublicProperty),
                    new MagicSet($originalClass, $servicePoolProperty, $publicProperties, $isPublicProperty),
                    new MagicClone($originalClass, $servicePoolProperty),
                    new Unserialize($originalClass, $servicePoolProperty),
                ]
            )
        );
    }
}
