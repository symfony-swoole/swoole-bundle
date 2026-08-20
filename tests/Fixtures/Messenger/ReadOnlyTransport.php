<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Messenger;

use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Ordinary in every way - deliberately not final, so that {@see ReadOnlyTransportFactory} being
 * read-only is the only thing standing between it and a pool.
 */
// phpcs:ignore SlevomatCodingStandard.Classes.RequireAbstractOrFinal.ClassNeitherAbstractNorFinal
class ReadOnlyTransport implements TransportInterface
{
    #[Override]
    public function get(): iterable
    {
        return [];
    }

    #[Override]
    public function ack(Envelope $envelope): void {}

    #[Override]
    public function reject(Envelope $envelope): void {}

    #[Override]
    public function send(Envelope $envelope): Envelope
    {
        return $envelope;
    }
}
