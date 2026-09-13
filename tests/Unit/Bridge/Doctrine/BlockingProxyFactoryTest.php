<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Doctrine;

use Doctrine\ORM\Proxy\ProxyFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionParameter;
use SwooleBundle\SwooleBundle\Bridge\Doctrine\BlockingProxyFactory;

#[CoversClass(BlockingProxyFactory::class)]
final class BlockingProxyFactoryTest extends TestCase
{
    /**
     * The factory extends Doctrine's and overrides getProxy(), so any parameter Doctrine adds to it is one
     * this class has to declare too - a subclass may not drop a parameter, and PHP refuses to load a class
     * that does. doctrine/orm 3.7 added `$assignIdentifiers`, and the class stopped loading.
     *
     * Nothing surfaced that where it happened. The factory is only put in place where native lazy objects
     * are off, which on PHP 8.4+ they are not, so the fatal appeared on PHP 8.3 alone, and there as eighty
     * feature tests failing to compile a container rather than as anything naming this class. Asking for the
     * method here loads the class on whichever doctrine/orm composer resolved, so the next such change fails
     * one unit test that says what broke.
     *
     * Compared by name and in order, and only as far as Doctrine's own list goes: declaring a parameter an
     * older Doctrine does not have is allowed, and is exactly how this stays compatible with 3.0-3.6.
     */
    public function testGetProxyDeclaresEveryParameterDoctrinesDoes(): void
    {
        $doctrines = self::parameterNames(new ReflectionMethod(ProxyFactory::class, 'getProxy'));
        $ours = self::parameterNames(new ReflectionMethod(BlockingProxyFactory::class, 'getProxy'));

        self::assertSame($doctrines, array_slice($ours, 0, count($doctrines)));
    }

    /**
     * @return list<string>
     */
    private static function parameterNames(ReflectionMethod $method): array
    {
        return array_map(
            static fn(ReflectionParameter $parameter): string => $parameter->getName(),
            $method->getParameters(),
        );
    }
}
