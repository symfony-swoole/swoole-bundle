<?php

declare(strict_types=1);

namespace K911\Swoole\Server\TaskHandler;

use Swoole\Server;

final class NoOpTaskFinishedHandler implements TaskFinishedHandlerInterface
{
    public function handle(Server $server, int $taskId, mixed $data): void
    {
        // noop
    }
}
