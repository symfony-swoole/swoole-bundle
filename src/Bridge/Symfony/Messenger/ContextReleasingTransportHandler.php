<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\CoWrapper;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandlerInterface;

final class ContextReleasingTransportHandler implements TaskHandlerInterface
{
    public function __construct(
        private readonly TaskHandlerInterface $decorated,
        private readonly CoWrapper $coWrapper
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
