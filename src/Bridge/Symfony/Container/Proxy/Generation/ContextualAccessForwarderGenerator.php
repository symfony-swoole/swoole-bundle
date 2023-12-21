<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container\Proxy\Generation;

use K911\Swoole\Bridge\Symfony\Container\Proxy\ContextualProxy;
use K911\Swoole\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\GetWrappedServicePoolValue;
use K911\Swoole\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\MagicGet;
use K911\Swoole\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\MagicSet;
use K911\Swoole\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator\StaticProxyConstructor;
use K911\Swoole\Bridge\Symfony\Container\Proxy\Generation\PropertyGenerator\ServicePoolProperty;
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\MethodGenerator;
use ProxyManager\Exception\InvalidProxiedClassException;
use ProxyManager\Generator\Util\ClassGeneratorUtils;
use ProxyManager\ProxyGenerator\Assertion\CanProxyAssertion;
use ProxyManager\ProxyGenerator\PropertyGenerator\PublicPropertiesMap;
use ProxyManager\ProxyGenerator\ProxyGeneratorInterface;
use ProxyManager\ProxyGenerator\Util\Properties;
use ProxyManager\ProxyGenerator\Util\ProxiedMethodsFilter;

/**
 * Generator for proxies with service pool.
 */
class ContextualAccessForwarderGenerator implements ProxyGeneratorInterface
{
    public function __construct(private readonly MethodForwarderBuilder $forwarderBuilder)
    {
    }

    /**
     * @template T of object
     *
     * @param \ReflectionClass<T> $originalClass
     *
     * @throws \InvalidArgumentException
     * @throws InvalidProxiedClassException
     */
    public function generate(\ReflectionClass $originalClass, ClassGenerator $classGenerator): void
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

        $publicProperties = new PublicPropertiesMap(Properties::fromReflectionClass($originalClass));
        $classGenerator->setImplementedInterfaces($interfaces);
        $classGenerator->addPropertyFromGenerator($servicePoolProperty = new ServicePoolProperty());
        $classGenerator->addPropertyFromGenerator($publicProperties);
        $closure = static function (MethodGenerator $generatedMethod) use ($originalClass, $classGenerator): void {
            ClassGeneratorUtils::addMethodIfNotFinal($originalClass, $classGenerator, $generatedMethod);
        };

        array_map(
            $closure,
            array_merge(
                array_map(
                    $this->forwarderBuilder->buildMethodInterceptor($servicePoolProperty),
                    ProxiedMethodsFilter::getProxiedMethods($originalClass)
                ),
                [
                    new StaticProxyConstructor($servicePoolProperty, Properties::fromReflectionClass($originalClass)),
                    new GetWrappedServicePoolValue($servicePoolProperty),
                    new MagicGet($originalClass, $servicePoolProperty, $publicProperties),
                    new MagicSet($originalClass, $servicePoolProperty, $publicProperties),
                ]
            )
        );
    }
}
