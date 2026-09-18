<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Container\Proxy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ProxyManager\Configuration;
use ProxyManager\Signature\Exception\InvalidSignatureException;
use ProxyManager\Signature\Exception\MissingSignatureException;
use ProxyManager\Signature\SignatureGenerator;
use ReflectionClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\ContextualAccessForwarderGenerator;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generator;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Signature\ReadonlyAwareClassSignatureGenerator;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Signature\ReadonlyAwareSignatureChecker;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\StaticServicePool;

/**
 * A readonly class can only be extended by a readonly class, so it is proxied by a readonly proxy.
 *
 * The questions are the ones a readonly proxy raises and an ordinary one does not: whether the class it
 * generates is one PHP will declare at all, whether the proxy still reaches the pooled instance through
 * both its methods and its public properties, and whether the signature ProxyManager checks a proxy by
 * survives having to move from a static property to a constant.
 */
#[CoversClass(ContextualAccessForwarderGenerator::class)]
#[CoversClass(ReadonlyAwareClassSignatureGenerator::class)]
#[CoversClass(ReadonlyAwareSignatureChecker::class)]
final class ReadonlyProxyGenerationTest extends TestCase
{
    public function testAReadonlyClassIsProxiedByAReadonlyProxy(): void
    {
        $proxy = $this->proxyOf(new ReadonlyHandleHolder('pooled'));

        self::assertInstanceOf(ReadonlyHandleHolder::class, $proxy);
        self::assertInstanceOf(ContextualProxy::class, $proxy);
        self::assertTrue((new ReflectionClass($proxy))->isReadOnly());
    }

    /**
     * What a readonly class may not declare, and what an ordinary proxy keeps in exactly that form: the
     * map of public properties and the signature. Both have to be constants here.
     */
    public function testTheProxyKeepsItsMapAndSignatureInConstants(): void
    {
        $proxyClass = new ReflectionClass($this->proxyOf(new ReadonlyHandleHolder('pooled')));

        self::assertSame([], $proxyClass->getStaticProperties());
        self::assertSame(
            ['publicProperties', 'signature'],
            array_map(
                self::withoutSuffix(...),
                array_keys($proxyClass->getConstants()),
            ),
        );
    }

    public function testMethodsAndPublicPropertiesReachThePooledInstance(): void
    {
        $pooled = new ReadonlyHandleHolder('pooled');
        $proxy = $this->proxyOf($pooled);
        self::assertInstanceOf(ReadonlyHandleHolder::class, $proxy);
        self::assertInstanceOf(ContextualProxy::class, $proxy);

        self::assertSame($pooled->handleId(), $proxy->handleId());
        self::assertSame('pooled', $proxy->name);
        self::assertSame($pooled, $proxy->getContextualObject());
    }

    /**
     * __clone gives the copy a pool of its own, which writes the proxy's one readonly property a second
     * time - allowed only because PHP lets __clone reinitialize a readonly property.
     */
    public function testACloneWrapsACloneOfThePooledInstance(): void
    {
        $pooled = new ReadonlyHandleHolder('pooled');
        $clone = clone $this->proxyOf($pooled);

        self::assertInstanceOf(ContextualProxy::class, $clone);
        self::assertInstanceOf(ReadonlyHandleHolder::class, $clone);
        self::assertNotSame($pooled, $clone->getContextualObject());
        self::assertSame('pooled', $clone->name);
    }

    /**
     * The control: an ordinary class gets exactly the proxy it always did, with the static property
     * ProxyManager signs it with and the static map - the configuration is shared with ProxyManager's own
     * factories, so this path must not have moved.
     */
    public function testAnOrdinaryClassKeepsTheStaticPropertiesItAlwaysHad(): void
    {
        $proxyClass = new ReflectionClass($this->proxyOf(new MutableHandleHolder()));

        self::assertFalse($proxyClass->isReadOnly());
        self::assertSame([], $proxyClass->getConstants());
        self::assertSame(
            ['publicProperties', 'signature'],
            array_map(
                self::withoutSuffix(...),
                array_keys($proxyClass->getStaticProperties()),
            ),
        );
    }

    public function testAReadonlyClassWithoutTheSignatureConstantIsRefused(): void
    {
        $this->expectException(MissingSignatureException::class);

        self::checker()->checkSignature(new ReflectionClass(ReadonlyHandleHolder::class), ['className' => 'x']);
    }

    /**
     * A proxy found on disk that was generated from different parameters - the case the signature exists
     * to catch. Declared by hand, because the constant's name is a hash of the parameters: no other
     * parameters produce the same name with a different value.
     */
    public function testAReadonlyClassWithAStaleSignatureIsRefused(): void
    {
        $parameters = ['className' => ReadonlyHandleHolder::class];
        $constant = ReadonlyAwareClassSignatureGenerator::constantName(new SignatureGenerator(), $parameters);
        /** @var class-string $class declared by the eval below */
        $class = 'StaleReadonlyProxy' . bin2hex(random_bytes(4));
        eval(sprintf("readonly class %s { private const %s = 'stale'; }", $class, $constant));

        $this->expectException(InvalidSignatureException::class);

        self::checker()->checkSignature(new ReflectionClass($class), $parameters);
    }

    /**
     * Answers a plain object on purpose: what the proxy is - a subclass, a contextual proxy - is what
     * these tests assert, so they narrow it themselves rather than take it on trust from a type.
     */
    private function proxyOf(object $instance): object
    {
        $signatureGenerator = new SignatureGenerator();
        $configuration = new Configuration();
        $configuration->setClassSignatureGenerator(new ReadonlyAwareClassSignatureGenerator($signatureGenerator));
        $configuration->setSignatureChecker(new ReadonlyAwareSignatureChecker($signatureGenerator));

        return (new Generator($configuration))->createProxy(new StaticServicePool($instance), $instance::class);
    }

    /**
     * The generated names carry a hash after a fixed prefix - and `signature` ends in a letter that is also
     * a hex digit, so the prefix is matched rather than the suffix stripped.
     */
    private static function withoutSuffix(string $name): string
    {
        foreach (['publicProperties', 'signature'] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return $prefix;
            }
        }

        return $name;
    }

    private static function checker(): ReadonlyAwareSignatureChecker
    {
        return new ReadonlyAwareSignatureChecker(new SignatureGenerator());
    }
}
