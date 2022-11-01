<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Messenger;

use K911\Swoole\Bridge\Symfony\Container\CoWrapper;
use K911\Swoole\Server\TaskHandler\TaskHandlerInterface;
use Swoole\Server;

final class ContextReleasingTransportHandler implements TaskHandlerInterface
{
    private TaskHandlerInterface $decorated;
    private CoWrapper $coWrapper;

    public function __construct(TaskHandlerInterface $decorated, CoWrapper $coWrapper)
    {
        $this->decorated = $decorated;
        $this->coWrapper = $coWrapper;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function handle(Server $server, Server\Task $task): void
    {
        $this->coWrapper->defer();
        $this->decorated->handle($server, $task);
    }
}
