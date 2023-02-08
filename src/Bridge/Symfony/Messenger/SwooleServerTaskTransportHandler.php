<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Messenger;

use Assert\Assertion;
use K911\Swoole\Server\TaskHandler\TaskHandlerInterface;
use Swoole\Server;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class SwooleServerTaskTransportHandler implements TaskHandlerInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private ?TaskHandlerInterface $decorated = null
    ) {
    }

    public function handle(Server $server, Server\Task $task): void
    {
        Assertion::isInstanceOf($task->data, Envelope::class);
        /* @var $data Envelope */
        $data = $task->data;
        $this->bus->dispatch($data);

        if ($this->decorated instanceof TaskHandlerInterface) {
            $this->decorated->handle($server, $task);
        }
    }
}
