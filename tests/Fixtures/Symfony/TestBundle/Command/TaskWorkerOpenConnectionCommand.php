<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command;

use Override;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\GoodbyeSayingConnection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Opens a pooled connection and ends by itself, so its task worker is recycled with the connection still in the
 * pool - the state a worker that sent mail is in when it goes.
 */
#[AsCommand(
    name: 'test:task-worker:open-connection',
    description: 'Opens a pooled connection and ends, so its task worker is recycled.',
)]
final class TaskWorkerOpenConnectionCommand extends Command
{
    /**
     * Longer than the second below which the runner treats a group that ended as broken rather than finished.
     */
    private const int RUNTIME_MICROSECONDS = 1_500_000;

    public function __construct(
        private readonly GoodbyeSayingConnection $connection,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->connection->open();
        usleep(self::RUNTIME_MICROSECONDS);

        return self::SUCCESS;
    }
}
