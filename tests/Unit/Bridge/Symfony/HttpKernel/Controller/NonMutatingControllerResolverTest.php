<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\HttpKernel\Controller;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel\Controller\NonMutatingControllerResolver;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller\AbstractBasedController;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller\IndexController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(NonMutatingControllerResolver::class)]
final class NonMutatingControllerResolverTest extends TestCase
{
    /**
     * The regression: FrameworkBundle's resolver writes the container twice on every request, to a
     * controller service that is shared for the whole life of the worker. Under coroutines the second
     * request to reach it is a cross-coroutine write.
     */
    public function testResolvingAControllerDoesNotWriteToIt(): void
    {
        $controller = new AbstractBasedController();
        $controller->setContainer(new ServiceLocator([])); // the compile-time injection

        self::assertSame(1, $controller->containerWrites());

        $resolver = $this->resolverFor(AbstractBasedController::class, $controller);

        $this->instantiate($resolver, AbstractBasedController::class);
        $this->instantiate($resolver, AbstractBasedController::class);
        $this->instantiate($resolver, AbstractBasedController::class);

        self::assertSame(
            1,
            $controller->containerWrites(),
            'Resolving must leave the controller untouched - only the compile-time injection may write.'
        );
    }

    public function testTheSharedControllerServiceIsHandedOutAsItIs(): void
    {
        $controller = new AbstractBasedController();
        $controller->setContainer(new ServiceLocator([]));
        $resolver = $this->resolverFor(AbstractBasedController::class, $controller);

        self::assertSame($controller, $this->instantiate($resolver, AbstractBasedController::class));
        self::assertSame($controller, $this->instantiate($resolver, AbstractBasedController::class));
    }

    /**
     * The diagnostic the writes used to serve has to survive them: a controller whose container was
     * never injected is a misconfigured service subscriber, and saying so beats a TypeError later.
     */
    public function testAControllerWithoutAnInjectedContainerIsRejected(): void
    {
        $resolver = $this->resolverFor(AbstractBasedController::class, new AbstractBasedController());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('has no container set, did you forget to define it as a service subscriber?');

        $this->instantiate($resolver, AbstractBasedController::class);
    }

    /**
     * Controllers not extending AbstractController have no container to check in the first place.
     */
    public function testAControllerOutsideTheAbstractControllerHierarchyIsLeftAlone(): void
    {
        $controller = new IndexController();
        $resolver = $this->resolverFor(IndexController::class, $controller);

        self::assertSame($controller, $this->instantiate($resolver, IndexController::class));
    }

    private function resolverFor(string $serviceId, object $controller): NonMutatingControllerResolver
    {
        $container = new Container();
        $container->set($serviceId, $controller);

        return new NonMutatingControllerResolver($container);
    }

    private function instantiate(NonMutatingControllerResolver $resolver, string $class): object
    {
        $instantiate = new ReflectionMethod($resolver, 'instantiateController');

        /** @var object $controller */
        $controller = $instantiate->invoke($resolver, $class);

        return $controller;
    }
}
