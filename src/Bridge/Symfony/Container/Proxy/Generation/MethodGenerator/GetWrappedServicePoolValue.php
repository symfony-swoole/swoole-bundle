<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator;

use K911\Swoole\Bridge\Symfony\Container\Proxy\Generation\PropertyGenerator\ServicePoolProperty;
use K911\Swoole\Bridge\Symfony\Container\ServicePool\ServicePool;
use Laminas\Code\Generator\Exception\InvalidArgumentException;
use ProxyManager\Generator\MethodGenerator;

/**
 * Implementation for {@see \ProxyManager\Proxy\ValueHolderInterface::getWrappedValueHolderValue}
 * for lazy loading value holder objects.
 */
class GetWrappedServicePoolValue extends MethodGenerator
{
    /**
     * Constructor.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(ServicePoolProperty $servicePoolHolderProperty)
    {
        parent::__construct('getServicePool');
        $this->setBody('return $this->'.$servicePoolHolderProperty->getName().';');
        $this->setReturnType(ServicePool::class);
    }
}
