<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger;

use Exception;
use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandler;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;

final readonly class ServiceResettingTransportHandler implements TaskHandler
{
    public function __construct(
        private TaskHandler $decorated,
        private ServicesResetter $resetter,
    ) {}

    /**
     * @throws Exception
     */
    public function handle(Server $server, Server\Task $task): void
    {
        $this->resetter->reset();
        $this->decorated->handle($server, $task);
    }
}
