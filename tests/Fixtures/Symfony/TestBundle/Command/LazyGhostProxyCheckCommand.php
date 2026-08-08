<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command;

use Override;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\LazyGhostExample;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Reports whether a lazy service arrives constructed.
 *
 * Reading a promoted property is the whole test: a ghost the factory handed back without running its
 * constructor answers everything else exactly like a working service.
 */
#[AsCommand(
    name: 'test:lazy-ghost:proxy-check',
    description: 'Tells whether a lazy service is constructed by the time it is used.',
)]
final class LazyGhostProxyCheckCommand extends Command
{
    public function __construct(
        private readonly LazyGhostExample $lazyGhost,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $output->writeln(sprintf('lazy ghost SAYS: %s', $this->lazyGhost->describe()));
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('lazy ghost FAILED: %s', $throwable->getMessage()));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
