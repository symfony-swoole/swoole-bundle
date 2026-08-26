<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Messenger;

use Override;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Fits the convention, and still cannot be pooled: wrapping a factory means extending it, and a
 * read-only class cannot be extended.
 */
final readonly class ReadOnlyTransportFactory implements TransportFactoryInterface
{
    #[Override]
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return new ReadOnlyTransport();
    }

    #[Override]
    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'read-only://');
    }
}
