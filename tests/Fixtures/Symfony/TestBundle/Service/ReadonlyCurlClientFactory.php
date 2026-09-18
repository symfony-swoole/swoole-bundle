<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service;

/**
 * A readonly factory of readonly curl clients, tagged `swoole_bundle.unmanaged_factory`.
 *
 * The clients it makes are not services, so the container cannot pool them; the factory is what gets
 * wrapped, and every client it hands out is a proxy pooling one client per coroutine. Both are readonly,
 * and both are final - the whole road a real pair takes.
 */
final readonly class ReadonlyCurlClientFactory
{
    public function __construct(public string $baseUri = 'http://localhost') {}

    public function create(): ReadonlyCurlClient
    {
        return new ReadonlyCurlClient($this->baseUri);
    }
}
