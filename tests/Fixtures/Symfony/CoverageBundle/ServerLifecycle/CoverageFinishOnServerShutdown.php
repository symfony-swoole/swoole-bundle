<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\ServerLifecycle;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\ServerShutdownHandler;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\Coverage\NameGenerator;

final class CoverageFinishOnServerShutdown implements ServerShutdownHandler
{
    public function __construct(
        private readonly CodeCoverageManager $codeCoverageManager,
        private readonly ?ServerShutdownHandler $decorated = null,
    ) {}

    public function handle(Server $server): void
    {
        if ($this->decorated instanceof ServerShutdownHandler) {
            $this->decorated->handle($server);
        }

        $this->codeCoverageManager->stop();
        $this->codeCoverageManager->finish(NameGenerator::nameForUseCase('test_server'));
    }
}
