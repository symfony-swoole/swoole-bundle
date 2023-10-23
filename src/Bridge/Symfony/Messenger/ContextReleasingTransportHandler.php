<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Messenger;

use K911\Swoole\Bridge\Symfony\Container\CoWrapper;
use K911\Swoole\Server\TaskHandler\TaskHandlerInterface;
use Swoole\Server;

final class ContextReleasingTransportHandler implements TaskHandlerInterface
{
    public function __construct(
        private TaskHandlerInterface $decorated,
        private CoWrapper $coWrapper
    ) {
    }

    /**
     * @throws \Exception
     */
    public function handle(Server $server, Server\Task $task): void
    {
        $this->coWrapper->defer();
        $this->decorated->handle($server, $task);
    }
}
