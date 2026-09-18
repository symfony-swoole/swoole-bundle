<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy;

use Closure;

/**
 * Everything a readonly access interceptor proxy changes after it has been built.
 *
 * ProxyManager's access interceptor keeps the wrapped object and its interceptors in properties of the
 * proxy itself, and its interface lets them be changed at any time - setMethodPrefixInterceptor() writes
 * into the map. A readonly proxy can do neither: its properties are written once. So a readonly one keeps
 * a single readonly property holding one of these, and everything that has to change later changes in
 * here. Readonly is not immutable: the property never points anywhere else, but the object it points to
 * is an ordinary one.
 *
 * Public, because the generated proxy reaches into it the way ProxyManager's generated code reaches into
 * its own properties - it is state of the proxy, kept out of the proxy only because it has to be.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\Generation\ReadonlyAccessInterceptorGenerator
 */
// phpcs:disable SlevomatCodingStandard.Classes.ForbiddenPublicProperty
// phpcs:ignore SlevomatCodingStandard.Classes.ReadonlyClass
final class AccessInterceptorState
{
    /**
     * @param array<string, Closure> $prefixInterceptors
     * @param array<string, Closure> $suffixInterceptors
     */
    public function __construct(
        public object $valueHolder,
        public array $prefixInterceptors = [],
        public array $suffixInterceptors = [],
    ) {}
}
