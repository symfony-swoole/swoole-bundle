<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container\Proxy\Generation;

use K911\Swoole\Bridge\Symfony\Container\Proxy\ContextualProxy;
use K911\Swoole\Bridge\Symfony\Container\ServicePool\ServicePool;
use ProxyManager\Configuration;
use ProxyManager\Factory\AbstractBaseFactory;
use ProxyManager\ProxyGenerator\ProxyGeneratorInterface;
use ProxyManager\Signature\Exception\InvalidSignatureException;
use ProxyManager\Signature\Exception\MissingSignatureException;

/**
 * Factory responsible of producing proxy objects.
 */
class ContextualAccessForwarderFactory extends AbstractBaseFactory
{
    private $generator;

    public function __construct(?Configuration $configuration = null)
    {
        parent::__construct($configuration);

        $this->generator = new ContextualAccessForwarderGenerator(new MethodForwarderBuilder());
    }

    /**
     * @template RealObjectType of object
     *
     * @param ServicePool<RealObjectType>  $servicePool
     * @param class-string<RealObjectType> $serviceClass
     *
     * @throws InvalidSignatureException
     * @throws MissingSignatureException
     * @throws \OutOfBoundsException
     *
     * @return ContextualProxy<RealObjectType>&RealObjectType
     */
    public function createProxy(ServicePool $servicePool, string $serviceClass)
    {
        /** @var class-string<ContextualProxy<RealObjectType>&RealObjectType> $proxyClassName */
        $proxyClassName = $this->generateProxy($serviceClass);

        return $proxyClassName::staticProxyConstructor($servicePool);
    }

    protected function getGenerator(): ProxyGeneratorInterface
    {
        return $this->generator;
    }
}
