<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\EventListeners;

use K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;

final class CoverageFinishOnConsoleTerminate
{
    public function __construct(private CodeCoverageManager $coverageManager)
    {
    }

    public function __invoke(ConsoleTerminateEvent $commandEvent): void
    {
        $this->coverageManager->stop();

        $slug = str_replace(['-', ':'], '_', $commandEvent->getCommand()->getName());
        $this->coverageManager->finish(sprintf('test_cmd_%s', $slug));
    }
}
