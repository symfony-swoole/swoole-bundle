<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Container\Proxy;

/**
 * A readonly factory, of the kind `swoole_bundle.unmanaged_factory` wraps.
 *
 * Not final, because removing `final` from a proxied class is z-engine's job, and z-engine is not loaded
 * in unit tests.
 */
// phpcs:ignore SlevomatCodingStandard.Classes.RequireAbstractOrFinal
readonly class ReadonlyClientFactory
{
    public function __construct(public string $baseUri = 'http://localhost') {}

    public function create(string $name): ReadonlyHandleHolder
    {
        return new ReadonlyHandleHolder($name);
    }
}
