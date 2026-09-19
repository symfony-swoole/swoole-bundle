<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy;

use Override;
use ProxyManager\Configuration;
use ProxyManager\Factory\AccessInterceptorValueHolderFactory;
use ProxyManager\ProxyGenerator\ProxyGeneratorInterface;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\ReadonlyAwareAccessInterceptorGenerator;

/**
 * ProxyManager's access interceptor factory, able to proxy a readonly class as well.
 *
 * Only the generator differs, and only for a readonly class - see
 * {@see ReadonlyAwareAccessInterceptorGenerator}. Creating, signing and checking a proxy is
 * ProxyManager's as before, which is also why this is a subclass: everything that asks for the factory by
 * its type still gets one.
 */
final class ReadonlyAwareAccessInterceptorValueHolderFactory extends AccessInterceptorValueHolderFactory
{
    private readonly ProxyGeneratorInterface $readonlyAwareGenerator;

    public function __construct(?Configuration $configuration = null)
    {
        parent::__construct($configuration);

        $this->readonlyAwareGenerator = new ReadonlyAwareAccessInterceptorGenerator();
    }

    #[Override]
    protected function getGenerator(): ProxyGeneratorInterface
    {
        return $this->readonlyAwareGenerator;
    }
}
