<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger;

use SwooleBundle\SwooleBundle\Server\HttpServer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class SwooleServerTaskTransportFactory implements TransportFactoryInterface
{
    public function __construct(private readonly HttpServer $server)
    {
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return new SwooleServerTaskTransport(
            new SwooleServerTaskReceiver(),
            new SwooleServerTaskSender($this->server)
        );
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === mb_strpos($dsn, 'swoole://task');
    }
}
