<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\TaskHandler;

use Swoole\Server;

interface TaskFinisher
{
    public function finish(Server\Task $task, mixed $data): void;
}
