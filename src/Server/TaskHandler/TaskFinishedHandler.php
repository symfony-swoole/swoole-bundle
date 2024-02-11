<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\TaskHandler;

use Swoole\Server;

/**
 * Task Finished Handler is called only when Task Handler returns any result or Swoole\Server->finish() is called.
 *
 * @see https://www.swoole.co.uk/docs/modules/swoole-server/callback-functions#onfinish
 */
interface TaskFinishedHandler
{
    public function handle(Server $server, int $taskId, mixed $data): void;
}
