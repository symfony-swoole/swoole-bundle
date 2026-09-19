<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Container\Proxy;

use ArrayObject;
use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ProxyManager\Configuration;
use ProxyManager\Proxy\AccessInterceptorValueHolderInterface;
use ProxyManager\Signature\SignatureGenerator;
use ReflectionClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\ReadonlyAccessInterceptorGenerator;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ReadonlyAwareAccessInterceptorValueHolderFactory;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Signature\ReadonlyAwareClassSignatureGenerator;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Signature\ReadonlyAwareSignatureChecker;

/**
 * The access interceptor a `swoole_bundle.unmanaged_factory` is wrapped in, for a factory that is readonly.
 *
 * What is at stake is ProxyManager's interface, kept whole by a class that cannot change its own
 * properties: the interceptors run around the wrapped object's methods, can be set after the proxy is
 * built, and a clone gets interceptors and a wrapped object of its own.
 */
#[CoversClass(ReadonlyAccessInterceptorGenerator::class)]
#[CoversClass(ReadonlyAwareAccessInterceptorValueHolderFactory::class)]
final class ReadonlyAccessInterceptorProxyTest extends TestCase
{
    public function testAReadonlyFactoryIsWrappedByAReadonlyInterceptor(): void
    {
        $proxy = self::proxyOf(new ReadonlyClientFactory());
        $proxyClass = new ReflectionClass($proxy);

        self::assertInstanceOf(ReadonlyClientFactory::class, $proxy);
        self::assertInstanceOf(AccessInterceptorValueHolderInterface::class, $proxy);
        self::assertTrue($proxyClass->isReadOnly());
        self::assertSame([], $proxyClass->getStaticProperties());
    }

    public function testMethodsReachTheWrappedObject(): void
    {
        $factory = new ReadonlyClientFactory();
        $proxy = self::proxyOf($factory);
        self::assertInstanceOf(ReadonlyClientFactory::class, $proxy);
        self::assertInstanceOf(AccessInterceptorValueHolderInterface::class, $proxy);

        self::assertSame('made', $proxy->create('made')->name);
        self::assertSame($factory, $proxy->getWrappedValueHolderValue());
        self::assertSame('http://localhost', $proxy->baseUri);
    }

    /**
     * What an unmanaged factory is wrapped for: the interceptor answers instead of the factory method.
     */
    public function testAPrefixInterceptorCanAnswerInsteadOfTheMethod(): void
    {
        $proxy = self::proxyOf(new ReadonlyClientFactory(), [
            'create' => self::answering('intercepted'),
        ]);
        self::assertInstanceOf(ReadonlyClientFactory::class, $proxy);

        self::assertSame('intercepted', $proxy->create('made')->name);
    }

    public function testASuffixInterceptorSeesWhatTheMethodReturned(): void
    {
        /** @var ArrayObject<int, string|null> $seen */
        $seen = new ArrayObject();
        $proxy = self::proxyOf(new ReadonlyClientFactory(), [], [
            'create' => static function (
                object $proxy,
                object $instance,
                string $method,
                array $params,
                mixed $returnValue,
                bool &$returnEarly, // phpcs:ignore
            ) use ($seen): void {
                $seen->append($returnValue instanceof ReadonlyHandleHolder ? $returnValue->name : null);
            },
        ]);
        self::assertInstanceOf(ReadonlyClientFactory::class, $proxy);

        $proxy->create('made');

        self::assertSame(['made'], $seen->getArrayCopy());
    }

    /**
     * The one thing a readonly proxy could not do with the interceptors in properties of its own: the
     * interface lets them be set after it is built.
     */
    public function testAnInterceptorCanBeSetAndRemovedAfterTheProxyIsBuilt(): void
    {
        $proxy = self::proxyOf(new ReadonlyClientFactory());
        self::assertInstanceOf(ReadonlyClientFactory::class, $proxy);
        self::assertInstanceOf(AccessInterceptorValueHolderInterface::class, $proxy);

        $proxy->setMethodPrefixInterceptor('create', self::answering('set later'));
        self::assertSame('set later', $proxy->create('made')->name);

        $proxy->setMethodPrefixInterceptor('create', null);
        self::assertSame('made', $proxy->create('made')->name);
    }

    public function testACloneHasInterceptorsAndAWrappedObjectOfItsOwn(): void
    {
        $proxy = self::proxyOf(new ReadonlyClientFactory(), [
            'create' => self::answering('original'),
        ]);
        $clone = clone $proxy;
        self::assertInstanceOf(ReadonlyClientFactory::class, $proxy);
        self::assertInstanceOf(ReadonlyClientFactory::class, $clone);
        self::assertInstanceOf(AccessInterceptorValueHolderInterface::class, $proxy);
        self::assertInstanceOf(AccessInterceptorValueHolderInterface::class, $clone);

        $clone->setMethodPrefixInterceptor('create', self::answering('clone'));

        self::assertSame('clone', $clone->create('made')->name);
        self::assertSame('original', $proxy->create('made')->name);
        self::assertNotSame($proxy->getWrappedValueHolderValue(), $clone->getWrappedValueHolderValue());
    }

    /**
     * A proxy built with `new` rather than through staticProxyConstructor() - which runs the original
     * constructor on a wrapped object it creates for itself.
     */
    public function testAProxyBuiltWithNewWrapsAnObjectItConstructed(): void
    {
        $proxyClass = self::proxyOf(new ReadonlyClientFactory())::class;

        $proxy = new $proxyClass('http://example.com');
        self::assertInstanceOf(AccessInterceptorValueHolderInterface::class, $proxy);

        $wrapped = $proxy->getWrappedValueHolderValue();
        self::assertInstanceOf(ReadonlyClientFactory::class, $wrapped);
        self::assertSame('http://example.com', $wrapped->baseUri);
    }

    public function testAProxySurvivesSerialisation(): void
    {
        $proxy = self::proxyOf(new ReadonlyClientFactory('http://example.com'));

        $restored = unserialize(serialize($proxy));

        self::assertInstanceOf(ReadonlyClientFactory::class, $restored);
        self::assertSame('http://example.com', $restored->baseUri);
    }

    /**
     * The control: a class that is not readonly is proxied by ProxyManager's own generator, with the
     * properties it has always had.
     */
    public function testAnOrdinaryClassIsStillProxiedByProxyManagerItself(): void
    {
        $proxyClass = new ReflectionClass(self::proxyOf(new MutableHandleHolder()));

        self::assertFalse($proxyClass->isReadOnly());
        self::assertNotSame([], $proxyClass->getStaticProperties());
        self::assertSame([], array_filter(
            array_map(static fn($property): string => $property->getName(), $proxyClass->getProperties()),
            static fn(string $name): bool => str_starts_with($name, 'interceptorState'),
        ));
    }

    /**
     * Answers a plain object on purpose: what the proxy is - the factory's subclass, an interceptor - is
     * what these tests assert, so they narrow it themselves rather than take it on trust from a type.
     *
     * @param array<string, Closure> $prefixInterceptors
     * @param array<string, Closure> $suffixInterceptors
     */
    private static function proxyOf(
        object $instance,
        array $prefixInterceptors = [],
        array $suffixInterceptors = [],
    ): object {
        return self::factory()->createProxy($instance, $prefixInterceptors, $suffixInterceptors);
    }

    private static function factory(): ReadonlyAwareAccessInterceptorValueHolderFactory
    {
        $signatureGenerator = new SignatureGenerator();
        $configuration = new Configuration();
        $configuration->setClassSignatureGenerator(new ReadonlyAwareClassSignatureGenerator($signatureGenerator));
        $configuration->setSignatureChecker(new ReadonlyAwareSignatureChecker($signatureGenerator));

        return new ReadonlyAwareAccessInterceptorValueHolderFactory($configuration);
    }

    private static function answering(string $name): Closure
    {
        return static function (
            object $proxy,
            object $instance,
            string $method,
            array $params,
            bool &$returnEarly, // phpcs:ignore
        ) use ($name): ReadonlyHandleHolder {
            $returnEarly = true;

            return new ReadonlyHandleHolder($name);
        };
    }
}
