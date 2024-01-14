<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Client;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Client\HttpClient;

final class HttpClientTest extends TestCase
{
    public function testThatClientSerializesProperly(): void
    {
        $host = 'fake';
        $port = 8888;
        $ssl = false;
        $options = ['testing' => 1];

        $client = HttpClient::fromDomain($host, $port, $ssl, $options);

        $expected = [
            'host' => $host,
            'port' => $port,
            'ssl' => $ssl,
            'options' => $options,
        ];

        self::assertSame($expected, $client->__serialize());

        $serializedClient = \serialize($client);
        $unserializedClient = \unserialize($serializedClient, ['allowed_classes' => [HttpClient::class]]);

        self::assertInstanceOf(HttpClient::class, $unserializedClient);
    }
}
