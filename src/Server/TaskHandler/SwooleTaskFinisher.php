<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\TaskHandler;

use Swoole\Server\Task;

final class SwooleTaskFinisher implements TaskFinisher
{
    public function finish(Task $task, mixed $data): void
    {
        $task->finish($data);
    }
}
