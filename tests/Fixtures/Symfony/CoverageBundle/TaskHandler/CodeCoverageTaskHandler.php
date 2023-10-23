<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\TaskHandler;

use K911\Swoole\Server\TaskHandler\TaskHandlerInterface;
use K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager;
use Swoole\Server;

final class CodeCoverageTaskHandler implements TaskHandlerInterface
{
    public function __construct(
        private TaskHandlerInterface $decorated,
        private CodeCoverageManager $codeCoverageManager
    ) {
    }

    public function handle(Server $server, Server\Task $task): void
    {
        $testName = sprintf('test_task_%d_%d_%s', $task->id, $task->worker_id, bin2hex(random_bytes(4)));
        $this->codeCoverageManager->start($testName);

        $this->decorated->handle($server, $task);

        $this->codeCoverageManager->stop();
        $this->codeCoverageManager->finish($testName);
    }
}
