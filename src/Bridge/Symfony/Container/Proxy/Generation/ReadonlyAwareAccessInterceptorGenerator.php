<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation;

use Laminas\Code\Generator\ClassGenerator;
use Override;
use ProxyManager\ProxyGenerator\AccessInterceptorValueHolderGenerator;
use ProxyManager\ProxyGenerator\ProxyGeneratorInterface;
use ReflectionClass;

/**
 * Proxies a readonly class with {@see ReadonlyAccessInterceptorGenerator}, and every other one
 * exactly as ProxyManager always has.
 */
final readonly class ReadonlyAwareAccessInterceptorGenerator implements ProxyGeneratorInterface
{
    public function __construct(
        private ProxyGeneratorInterface $ordinaryClasses = new AccessInterceptorValueHolderGenerator(),
        private ProxyGeneratorInterface $readonlyClasses = new ReadonlyAccessInterceptorGenerator(),
    ) {}

    /**
     * @template T of object
     * @param ReflectionClass<T> $originalClass
     */
    #[Override]
    public function generate(ReflectionClass $originalClass, ClassGenerator $classGenerator): void
    {
        $generator = $originalClass->isReadOnly() ? $this->readonlyClasses : $this->ordinaryClasses;
        $generator->generate($originalClass, $classGenerator);
    }
}
