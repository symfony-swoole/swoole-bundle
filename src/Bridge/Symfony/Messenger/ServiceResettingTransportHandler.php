<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Messenger;

use K911\Swoole\Server\TaskHandler\TaskHandlerInterface;
use Swoole\Server;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;

final class ServiceResettingTransportHandler implements TaskHandlerInterface
{
    public function __construct(
        private readonly TaskHandlerInterface $decorated,
        private readonly ServicesResetter $resetter
    ) {
    }

    /**
     * @throws \Exception
     */
    public function handle(Server $server, Server\Task $task): void
    {
        $this->resetter->reset();
        $this->decorated->handle($server, $task);
    }
}
