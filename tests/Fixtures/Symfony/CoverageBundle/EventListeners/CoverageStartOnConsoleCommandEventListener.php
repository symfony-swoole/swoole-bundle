<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\EventListeners;

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\Coverage\NameGenerator;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

final readonly class CoverageStartOnConsoleCommandEventListener
{
    public function __construct(private CodeCoverageManager $coverageManager) {}

    public function __invoke(ConsoleCommandEvent $commandEvent): void
    {
        $this->coverageManager->start(
            NameGenerator::nameForUseCaseAndCommand('test_cmd', $commandEvent->getCommand()->getName())
        );
    }
}
