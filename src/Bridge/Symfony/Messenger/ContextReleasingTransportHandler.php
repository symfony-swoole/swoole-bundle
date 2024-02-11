<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger;

use Exception;
use Swoole\Server;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\CoWrapper;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandler;

final class ContextReleasingTransportHandler implements TaskHandler
{
    public function __construct(
        private readonly TaskHandler $decorated,
        private readonly CoWrapper $coWrapper,
    ) {}

    /**
     * @throws Exception
     */
    public function handle(Server $server, Server\Task $task): void
    {
        $this->coWrapper->defer();
        $this->decorated->handle($server, $task);
    }
}
