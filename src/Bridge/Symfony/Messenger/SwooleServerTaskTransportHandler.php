<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger;

use Assert\Assertion;
use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

final readonly class SwooleServerTaskTransportHandler implements TaskHandler
{
    public function __construct(
        private MessageBusInterface $bus,
        private ?TaskHandler $decorated = null,
    ) {}

    public function handle(Server $server, Server\Task $task): void
    {
        Assertion::isInstanceOf($task->data, Envelope::class);
        /** @var Envelope $data */
        $data = $task->data;

        $sentStamp = $data->last(SentStamp::class);
        $alias = null === $sentStamp ? 'swoole-task' : ($sentStamp->getSenderAlias() ?: $sentStamp->getSenderClass());

        $this->bus->dispatch($data->with(new ReceivedStamp($alias), new ConsumedByWorkerStamp()));

        if (!($this->decorated instanceof TaskHandler)) {
            return;
        }

        $this->decorated->handle($server, $task);
    }
}
