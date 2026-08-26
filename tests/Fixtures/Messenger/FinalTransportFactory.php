<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Messenger;

use Override;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Fits the convention - it builds {@see FinalTransport} beside it - but that class is final.
 */
final class FinalTransportFactory implements TransportFactoryInterface
{
    #[Override]
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return new FinalTransport();
    }

    #[Override]
    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'final://');
    }
}
