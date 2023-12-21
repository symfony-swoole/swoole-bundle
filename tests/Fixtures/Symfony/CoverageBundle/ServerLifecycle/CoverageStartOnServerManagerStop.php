<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\ServerLifecycle;

use K911\Swoole\Server\LifecycleHandler\ServerManagerStopHandlerInterface;
use K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager;
use K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\Coverage\NameGenerator;
use Swoole\Server;

final class CoverageStartOnServerManagerStop implements ServerManagerStopHandlerInterface
{
    public function __construct(
        private readonly CodeCoverageManager $codeCoverageManager,
        private readonly ?ServerManagerStopHandlerInterface $decorated = null
    ) {
    }

    public function handle(Server $server): void
    {
        if ($this->decorated instanceof ServerManagerStopHandlerInterface) {
            $this->decorated->handle($server);
        }

        $this->codeCoverageManager->stop();
        $this->codeCoverageManager->finish(NameGenerator::nameForUseCase('test_manager'));
    }
}
