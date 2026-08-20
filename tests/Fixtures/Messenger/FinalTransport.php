<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Messenger;

use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * A transport nothing can extend, and so nothing can generate a pool proxy from.
 */
final class FinalTransport implements TransportInterface
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
