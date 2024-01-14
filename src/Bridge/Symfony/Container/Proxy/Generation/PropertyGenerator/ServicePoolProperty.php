<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\PropertyGenerator;

use Laminas\Code\Generator\DocBlockGenerator;
use Laminas\Code\Generator\Exception\InvalidArgumentException;
use Laminas\Code\Generator\PropertyGenerator;
use Laminas\Code\Generator\TypeGenerator;
use ProxyManager\Generator\Util\IdentifierSuffixer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePool;

/**
 * Property that contains the wrapped Symfony container.
 */
class ServicePoolProperty extends PropertyGenerator
{
    /**
     * @var null|\ReflectionClass<ServicePool<object>>
     */
    private static ?\ReflectionClass $servicePoolReflection = null;

    /**
     * Constructor.
     *
     * @throws InvalidArgumentException
     */
    public function __construct()
    {
        parent::__construct(IdentifierSuffixer::getIdentifier('servicePool'));

        $docBlock = new DocBlockGenerator();

        $docBlock->setWordWrap(false);
        $docBlock->setLongDescription('@var \\'.self::getServicePoolReflection()->getName().' ServicePool holder');
        $this->setDocBlock($docBlock);
        $this->setVisibility(self::VISIBILITY_PRIVATE);
        $this->setType(TypeGenerator::fromTypeString(ServicePool::class));
        $this->omitDefaultValue();
    }

    /**
     * @return \ReflectionClass<ServicePool<object>>
     */
    private static function getServicePoolReflection(): \ReflectionClass
    {
        if (null === self::$servicePoolReflection) {
            /** @var \ReflectionClass<ServicePool<object>> $reflection */
            $reflection = new \ReflectionClass(ServicePool::class);
            self::$servicePoolReflection = $reflection;
        }

        return self::$servicePoolReflection;
    }
}
