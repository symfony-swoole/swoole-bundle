<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Api;

use Assert\Assertion;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Server\Config\Sockets;

final class ApiServerClientFactory
{
    public function __construct(private readonly Sockets $sockets) {}

    /**
     * Create new API Server client.
     *
     * @param array<string, mixed> $options
     */
    public function newClient(array $options = []): ApiServerClient
    {
        Assertion::true(
            $this->sockets->hasApiSocket(),
            'Swoole HTTP Server is not configured properly. '
            . 'To access API trough HTTP interface, you must enable '
            . 'and provide proper address of configured API Server.'
        );

        return new ApiServerClient(HttpClient::fromSocket(
            $this->sockets->getApiSocket(),
            $options
        ));
    }
}
