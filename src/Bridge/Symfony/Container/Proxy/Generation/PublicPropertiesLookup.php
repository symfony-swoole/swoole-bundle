<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation;

use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\PropertyGenerator;
use Laminas\Code\Generator\PropertyValueGenerator;
use ProxyManager\ProxyGenerator\PropertyGenerator\PublicPropertiesMap;

/**
 * Adds a proxy's map of its parent's public properties, and says how to ask it about a name.
 *
 * A static property on an ordinary proxy, as ProxyManager has always generated it, and a private constant
 * on a readonly one, which cannot declare a static property. The question differs with it: `isset()`
 * cannot take an element of a constant, so the constant is asked with array_key_exists() instead.
 */
final class PublicPropertiesLookup
{
    /**
     * @return string the condition that is true when `$name` names a public property of the parent
     */
    public static function add(ClassGenerator $classGenerator, PublicPropertiesMap $publicProperties): string
    {
        if (!$classGenerator->isReadonly()) {
            $classGenerator->addPropertyFromGenerator($publicProperties);

            return 'isset(self::$' . $publicProperties->getName() . '[$name])';
        }

        $classGenerator->addConstantFromGenerator(new PropertyGenerator(
            $publicProperties->getName(),
            new PropertyValueGenerator($publicProperties->getDefaultValue()?->getValue() ?? []),
            PropertyGenerator::FLAG_CONSTANT | PropertyGenerator::FLAG_PRIVATE,
        ));

        return '\\array_key_exists($name, self::' . $publicProperties->getName() . ')';
    }
}
