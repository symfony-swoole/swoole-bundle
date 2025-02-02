<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\EventListeners;

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\Coverage\NameGenerator;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;

final readonly class CoverageFinishOnConsoleTerminate
{
    public function __construct(private CodeCoverageManager $coverageManager) {}

    public function __invoke(ConsoleTerminateEvent $commandEvent): void
    {
        $this->coverageManager->stop();
        $this->coverageManager->finish(
            NameGenerator::nameForUseCaseAndCommand('test_cmd', $commandEvent->getCommand()->getName())
        );
    }
}
