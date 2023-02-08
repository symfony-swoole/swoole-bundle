<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\ServerLifecycle;

use K911\Swoole\Server\LifecycleHandler\ServerManagerStopHandlerInterface;
use K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager;
use Swoole\Server;

final class CoverageStartOnServerManagerStop implements ServerManagerStopHandlerInterface
{
    public function __construct(
        private CodeCoverageManager $codeCoverageManager,
        private ?ServerManagerStopHandlerInterface $decorated = null
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Server $server): void
    {
        if ($this->decorated instanceof ServerManagerStopHandlerInterface) {
            $this->decorated->handle($server);
        }

        $this->codeCoverageManager->stop();
        $this->codeCoverageManager->finish('test_manager');
    }
}
