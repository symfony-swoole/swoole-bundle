<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\WorkerHandler;

use Swoole\Server;

interface WorkerExitHandler
{
    /**
     * Handle onWorkerStop event.
     * Info: Function will be executed in worker process.
     *
     * To differentiate between server worker and task worker use snippet:
     *
     *      ```php
     *      if($server->taskworker) {
     *        echo "Hello from task worker process";
     *      }
     *      ```
     */
    public function handle(Server $worker, int $workerId): void;
}
