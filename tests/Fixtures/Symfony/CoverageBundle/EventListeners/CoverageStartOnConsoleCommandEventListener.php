<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\EventListeners;

use K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager;
use K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\Coverage\NameGenerator;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

final class CoverageStartOnConsoleCommandEventListener
{
    public function __construct(private readonly CodeCoverageManager $coverageManager)
    {
    }

    public function __invoke(ConsoleCommandEvent $commandEvent): void
    {
        $this->coverageManager->start(
            NameGenerator::nameForUseCaseAndCommand('test_cmd', $commandEvent->getCommand()->getName())
        );
    }
}
