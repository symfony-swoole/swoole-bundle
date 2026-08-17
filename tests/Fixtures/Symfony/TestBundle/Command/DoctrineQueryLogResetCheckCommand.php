<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command;

use Doctrine\DBAL\Connection;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Reports whether Symfony's service resetter empties Doctrine's query log.
 *
 * With profiling on, BacktraceDebugDataHolder keeps every query it has seen plus a debug_backtrace()
 * with each of them, and only reset() empties it. A process that serves requests is reset between them;
 * a worker is reset between jobs. Either way something has to reach the log, and if nothing does it
 * grows for as long as the process lives.
 *
 * The unit tests cover what the processor does to the container. This covers whether that adds up to a
 * log that is actually emptied once the whole container has been compiled - which is the part the
 * container assertions cannot see, because the reduction that used to undo it happens later.
 */
#[AsCommand(
    name: 'test:doctrine:query-log-reset-check',
    description: 'Tells whether the service resetter empties Doctrine\'s query log.',
)]
final class DoctrineQueryLogResetCheckCommand extends Command
{
    public function __construct(
        private readonly object $queryLog,
        private readonly ResetInterface $servicesResetter,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        for ($i = 0; $i < 3; $i++) {
            $this->connection->executeQuery('SELECT 1');
        }

        $output->writeln(sprintf('queries logged: %d', $this->countLoggedQueries()));

        $this->servicesResetter->reset();

        $output->writeln(sprintf('queries logged after reset: %d', $this->countLoggedQueries()));

        return self::SUCCESS;
    }

    private function countLoggedQueries(): int
    {
        $total = 0;
        /** @var array<string, array<int, mixed>> $data */
        $data = $this->queryLog->getData();

        foreach ($data as $queries) {
            $total += count($queries);
        }

        return $total;
    }
}
