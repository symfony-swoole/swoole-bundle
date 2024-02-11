<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\TaskHandler;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandler;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager;

final class CodeCoverageTaskHandler implements TaskHandler
{
    public function __construct(
        private readonly TaskHandler $decorated,
        private readonly CodeCoverageManager $codeCoverageManager,
    ) {}

    public function handle(Server $server, Server\Task $task): void
    {
        $testName = sprintf('test_task_%d_%d_%s', $task->id, $task->worker_id, bin2hex(random_bytes(4)));
        $this->codeCoverageManager->start($testName);

        $this->decorated->handle($server, $task);

        $this->codeCoverageManager->stop();
        $this->codeCoverageManager->finish($testName);
    }
}
