<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\Exception\ReceiverNotAvailableException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

final class SwooleServerTaskReceiver implements ReceiverInterface
{
    /**
     * {@inheritDoc}
     */
    public function get(): iterable
    {
        throw ReceiverNotAvailableException::make();
    }

    public function ack(Envelope $envelope): void
    {
        throw ReceiverNotAvailableException::make();
    }

    public function reject(Envelope $envelope): void
    {
        throw ReceiverNotAvailableException::make();
    }
}
