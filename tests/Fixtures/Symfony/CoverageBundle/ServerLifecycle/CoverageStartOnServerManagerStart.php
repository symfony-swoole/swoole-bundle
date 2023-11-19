<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\ServerLifecycle;

use K911\Swoole\Server\LifecycleHandler\ServerManagerStartHandlerInterface;
use K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager;
use K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\Coverage\NameGenerator;
use Swoole\Server;

final class CoverageStartOnServerManagerStart implements ServerManagerStartHandlerInterface
{
    public function __construct(
        private CodeCoverageManager $codeCoverageManager,
        private ?ServerManagerStartHandlerInterface $decorated = null
    ) {
    }

    public function handle(Server $server): void
    {
        $this->codeCoverageManager->start(NameGenerator::nameForUseCase('test_manager'));

        if ($this->decorated instanceof ServerManagerStartHandlerInterface) {
            $this->decorated->handle($server);
        }
    }
}
