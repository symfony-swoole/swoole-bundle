<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator;

use Laminas\Code\Generator\ParameterGenerator;
use Laminas\Code\Generator\PropertyGenerator;
use ProxyManager\Generator\MagicMethodGenerator;
use ReflectionClass;

// phpcs:disable Generic.Files.LineLength.TooLong
// phpcs:disable SlevomatCodingStandard.Files.LineLength.LineTooLong
/**
 * Magic `__unserialize` for lazy loading value holder objects
 */
final class Unserialize extends MagicMethodGenerator
{
    private const string TEMPLATE = <<<'PHP'
            $reflection = new \ReflectionClass(get_parent_class());
            $object = $reflection->newInstanceWithoutConstructor();
            $object->__unserialize($data);
            $this->{{$servicePoolPropertyName}} = new \SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\StaticServicePool($object);
        PHP;

    /**
     * Constructor.
     *
     * @template T of object
     * @param ReflectionClass<T> $originalClass
     */
    public function __construct(
        ReflectionClass $originalClass,
        PropertyGenerator $servicePoolProperty,
    ) {
        parent::__construct($originalClass, '__unserialize', [new ParameterGenerator('data')]);

        $servicePoolPropertyName = $servicePoolProperty->getName();
        $replacements = [
            '{{$servicePoolPropertyName}}' => $servicePoolPropertyName,
        ];

        $this->setBody(str_replace(
            array_keys($replacements),
            $replacements,
            self::TEMPLATE
        ));
    }
}
