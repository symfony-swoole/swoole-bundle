<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\ServerLifecycle;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\ServerManagerStartHandler;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\Coverage\NameGenerator;

final class CoverageStartOnServerManagerStart implements ServerManagerStartHandler
{
    public function __construct(
        private readonly CodeCoverageManager $codeCoverageManager,
        private readonly ?ServerManagerStartHandler $decorated = null,
    ) {}

    public function handle(Server $server): void
    {
        $this->codeCoverageManager->start(NameGenerator::nameForUseCase('test_manager'));

        if (!($this->decorated instanceof ServerManagerStartHandler)) {
            return;
        }

        $this->decorated->handle($server);
    }
}
