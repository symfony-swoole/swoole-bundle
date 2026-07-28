<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\MethodGenerator;

use Laminas\Code\Generator\PropertyGenerator;
use ProxyManager\Generator\MagicMethodGenerator;
use ReflectionClass;

// phpcs:disable Generic.Files.LineLength.TooLong
// phpcs:disable SlevomatCodingStandard.Files.LineLength.LineTooLong
/**
 * Magic `__clone` for lazy loading value holder objects
 */
final class MagicClone extends MagicMethodGenerator
{
    private const string TEMPLATE = <<<'PHP'
            $wrapped = $this->{{$servicePoolPropertyName}}->get();
            $this->{{$servicePoolPropertyName}} = new \SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\StaticServicePool(clone $wrapped);
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
        parent::__construct($originalClass, '__clone');

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
