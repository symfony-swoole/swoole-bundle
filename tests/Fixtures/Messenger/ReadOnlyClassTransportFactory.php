<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Messenger;

use Override;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Fits the convention - it builds {@see ReadOnlyClassTransport} beside it - and that class is read-only,
 * which a readonly proxy stands in for like any other.
 */
final class ReadOnlyClassTransportFactory implements TransportFactoryInterface
{
    #[Override]
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return new ReadOnlyClassTransport();
    }

    #[Override]
    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'read-only-class://');
    }
}
