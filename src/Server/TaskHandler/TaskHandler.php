<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\TaskHandler;

use Swoole\Server;

interface TaskHandler
{
    public function handle(Server $server, Server\Task $task): void;
}
