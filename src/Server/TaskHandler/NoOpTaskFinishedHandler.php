<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\TaskHandler;

use Swoole\Server;

final class NoOpTaskFinishedHandler implements TaskFinishedHandler
{
    public function handle(Server $server, int $taskId, mixed $data): void
    {
        // noop
    }
}
