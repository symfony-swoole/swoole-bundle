<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel\Controller;

use LogicException;
use Override;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Controller\ContainerControllerResolver;

/**
 * Replaces FrameworkBundle's ControllerResolver with one that does not write to the controller.
 *
 * A controller registered as a service is shared: the resolver hands out the same instance for every
 * request, for the whole life of the worker. FrameworkBundle's resolver nevertheless writes to it on
 * every single request, to check that its container was injected:
 *
 * ```php
 * if (null === $previousContainer = $controller->setContainer($this->container)) {
 *     throw new LogicException(...);
 * }
 *
 * $controller->setContainer($previousContainer);
 * ```
 *
 * Two writes that cancel each other out - the property ends up holding exactly what it held before, the
 * service subscriber locator injected at compile time. Nothing is configured here; the write is only how
 * the check reads the property.
 *
 * Under coroutines that is enough to break: requests are served concurrently, so the shared controller
 * is written to by whichever coroutines happen to be in flight, and concurrency checks reject the write
 * as one coroutine reaching into an object another one is using.
 *
 * This resolver keeps the check and drops the writes by reading the property directly. Controllers stay
 * shared and unproxied, and no per-request state is touched at all.
 */
final class NonMutatingControllerResolver extends ContainerControllerResolver
{
    private ?ReflectionProperty $containerProperty = null;

    #[Override]
    protected function instantiateController(string $class): object
    {
        $controller = parent::instantiateController($class);

        if ($controller instanceof AbstractController && !$this->hasContainer($controller)) {
            throw new LogicException(
                sprintf('"%s" has no container set, did you forget to define it as a service subscriber?', $class),
            );
        }

        return $controller;
    }

    /**
     * AbstractController::$container is typed and has no default, so it stays uninitialized until
     * something injects it - which is exactly what the check is after, and what setContainer() returning
     * null used to stand for.
     */
    private function hasContainer(AbstractController $controller): bool
    {
        $this->containerProperty ??= new ReflectionProperty(AbstractController::class, 'container');

        return $this->containerProperty->isInitialized($controller);
    }
}
