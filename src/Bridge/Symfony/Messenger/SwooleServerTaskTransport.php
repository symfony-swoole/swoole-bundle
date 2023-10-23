<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class SwooleServerTaskTransport implements TransportInterface
{
    public function __construct(
        private SwooleServerTaskReceiver $receiver,
        private SwooleServerTaskSender $sender
    ) {
    }

    public function send(Envelope $envelope): Envelope
    {
        return $this->sender->send($envelope);
    }

    public function get(): iterable
    {
        return $this->receiver->get();
    }

    public function ack(Envelope $envelope): void
    {
        $this->receiver->ack($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->receiver->reject($envelope);
    }
}
