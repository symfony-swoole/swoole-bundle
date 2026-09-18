<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service;

use Assert\Assertion;
use CurlHandle;

/**
 * A readonly HTTP client holding a curl handle - the case readonly proxies were written for.
 *
 * Readonly is not stateless. The property never changes, but the handle it holds is a connection with
 * state of its own - the socket it keeps alive, the options set on it, the transfer in progress - and two
 * coroutines driving one handle at once corrupt each other's requests. Nor can the handle be pooled on its
 * own: it is not a service. So the client is the unit that has to be pooled, one per coroutine, which
 * takes a proxy - and a readonly class can only be extended by a readonly one.
 */
final readonly class ReadonlyCurlClient
{
    private CurlHandle $handle;

    public function __construct(public string $baseUri = 'http://localhost')
    {
        $handle = curl_init();
        Assertion::isInstanceOf($handle, CurlHandle::class);

        $this->handle = $handle;
    }

    /**
     * Which handle this instance drives - what has to differ between two coroutines using the client.
     */
    public function handleId(): int
    {
        return spl_object_id($this->handle);
    }
}
