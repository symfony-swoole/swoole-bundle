<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\TaskWorker;

use Swoole\Server;

/**
 * A Server whose taskworker flag and worker_num can be set per test.
 *
 * One instance, reconfigured rather than rebuilt: constructing a Swoole\Server binds its port, so a
 * fresh one per test case would collide with itself.
 *
 * Nothing is overridden - only public properties the handler reads are written - which keeps this
 * working on both swoole and openswoole, whose method signatures differ.
 */
final class TaskWorkerServerMock extends Server
{
    private static ?self $instance = null;

    private function __construct()
    {
        parent::__construct('localhost', 31997);
    }

    public static function make(bool $taskworker, int $workerCount = 1): Server
    {
        $server = self::$instance ??= new self();
        $server->taskworker = $taskworker;
        $server->setting = ['worker_num' => $workerCount];

        return $server;
    }
}
