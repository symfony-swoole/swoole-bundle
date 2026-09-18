<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Messenger;

use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * A read-only transport - deliberately not final, so that being read-only is the only thing about it
 * that could stand between it and a pool.
 */
// phpcs:ignore SlevomatCodingStandard.Classes.RequireAbstractOrFinal.ClassNeitherAbstractNorFinal
readonly class ReadOnlyClassTransport implements TransportInterface
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
