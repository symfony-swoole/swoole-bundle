<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\ServerLifecycle;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\ServerStartHandler;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\Coverage\NameGenerator;

final readonly class CoverageStartOnServerStart implements ServerStartHandler
{
    public function __construct(
        private CodeCoverageManager $codeCoverageManager,
        private ?ServerStartHandler $decorated = null,
    ) {}

    public function handle(Server $server): void
    {
        $this->codeCoverageManager->start(NameGenerator::nameForUseCase('test_server'));

        if (!($this->decorated instanceof ServerStartHandler)) {
            return;
        }

        $this->decorated->handle($server);
    }
}
