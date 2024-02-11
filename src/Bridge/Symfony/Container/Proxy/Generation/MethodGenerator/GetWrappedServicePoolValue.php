<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator;

use Laminas\Code\Generator\Exception\InvalidArgumentException;
use ProxyManager\Generator\MethodGenerator;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\PropertyGenerator\ServicePoolProperty;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePool;

/**
 * Implementation for {@see \ProxyManager\Proxy\ValueHolderInterface::getWrappedValueHolderValue}
 * for lazy loading value holder objects.
 */
final class GetWrappedServicePoolValue extends MethodGenerator
{
    /**
     * Constructor.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(ServicePoolProperty $servicePoolHolderProperty)
    {
        parent::__construct('getServicePool');

        $this->setBody('return $this->' . $servicePoolHolderProperty->getName() . ';');
        $this->setReturnType(ServicePool::class);
    }
}
