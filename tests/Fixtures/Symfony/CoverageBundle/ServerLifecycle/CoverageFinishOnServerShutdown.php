<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\ServerLifecycle;

use K911\Swoole\Server\LifecycleHandler\ServerShutdownHandlerInterface;
use K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager;
use K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\Coverage\NameGenerator;
use Swoole\Server;

final class CoverageFinishOnServerShutdown implements ServerShutdownHandlerInterface
{
    public function __construct(
        private CodeCoverageManager $codeCoverageManager,
        private ?ServerShutdownHandlerInterface $decorated = null
    ) {
    }

    public function handle(Server $server): void
    {
        if ($this->decorated instanceof ServerShutdownHandlerInterface) {
            $this->decorated->handle($server);
        }

        $this->codeCoverageManager->stop();
        $this->codeCoverageManager->finish(NameGenerator::nameForUseCase('test_server'));
    }
}
