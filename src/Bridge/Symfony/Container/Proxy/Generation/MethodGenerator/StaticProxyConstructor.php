<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator;

use Laminas\Code\Generator\Exception\InvalidArgumentException;
use Laminas\Code\Generator\ParameterGenerator;
use Laminas\Code\Generator\PropertyGenerator;
use ProxyManager\Generator\MethodGenerator;
use ProxyManager\ProxyGenerator\Util\Properties;
use ProxyManager\ProxyGenerator\Util\UnsetPropertiesGenerator;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePool;

/**
 * The `staticProxyConstructor` implementation for lazy loading proxies.
 */
final class StaticProxyConstructor extends MethodGenerator
{
    /**
     * Static constructor.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(PropertyGenerator $servicePoolProperty, Properties $properties)
    {
        parent::__construct('staticProxyConstructor', [], self::FLAG_PUBLIC | self::FLAG_STATIC);

        $servicePoolParameter = new ParameterGenerator('servicePool', ServicePool::class);
        $servicePoolParameter->omitDefaultValue();
        $this->setParameter($servicePoolParameter);

        $this->setDocBlock(
            sprintf("Constructor for lazy initialization\n\n@param %s<object> \$servicePool", ServicePool::class)
        );
        $this->setBody(
            'static $reflection;' . "\n\n"
            . '$reflection = $reflection ?? new \ReflectionClass(__CLASS__);' . "\n"
            . '$instance   = $reflection->newInstanceWithoutConstructor();' . "\n\n"
            . UnsetPropertiesGenerator::generateSnippet($properties, 'instance')
            . '$instance->' . $servicePoolProperty->getName() . ' = $servicePool;' . "\n\n"
            . 'return $instance;'
        );
        $this->setReturnType('object');
    }
}
