<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger;

use SwooleBundle\SwooleBundle\Server\HttpServer;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

final class SwooleServerTaskSender implements SenderInterface
{
    public function __construct(private readonly HttpServer $httpServer) {}

    public function send(Envelope $envelope): Envelope
    {
        /** @var SentStamp|null $sentStamp */
        $sentStamp = $envelope->last(SentStamp::class);
        $alias = $sentStamp === null ? 'swoole-task' : $sentStamp->getSenderAlias() ?? $sentStamp->getSenderClass();

        $this->httpServer->dispatchTask($envelope->with(new ReceivedStamp($alias)));

        return $envelope;
    }
}
