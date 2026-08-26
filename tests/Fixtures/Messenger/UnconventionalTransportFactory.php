<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Messenger;

use LogicException;
use Override;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * A transport factory that does not build the class beside it - there is no UnconventionalTransport.
 *
 * Stands in for an application's own factory, which the convention MessengerProcessor reads the class
 * off has no reason to fit.
 */
final class UnconventionalTransportFactory implements TransportFactoryInterface
{
    #[Override]
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        throw new LogicException('Never built; this fixture exists for its supports().');
    }

    #[Override]
    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'unconventional://');
    }
}
