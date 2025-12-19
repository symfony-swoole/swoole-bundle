<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\TaskHandler;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\ConcurrentTasks\Task;

final readonly class ConcurrentTaskHandler implements TaskHandler
{
    public function __construct(
        private TaskHandler $decorated,
        private TaskFinisher $taskFinisher,
    ) {}

    public function handle(Server $server, Server\Task $task): void
    {
        if ($task->data instanceof Task) {
            $this->taskFinisher->finish($task, ($task->data->getCallback())());

            return;
        }

        $this->decorated->handle($server, $task);
    }
}
