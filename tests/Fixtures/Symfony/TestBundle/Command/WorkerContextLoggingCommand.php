<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command;

use Override;
use Psr\Log\LoggerInterface;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\CoWrapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A long running command that logs from inside a task worker, so a test can read back what the log says
 * about where the line came from.
 *
 * It logs twice on purpose. The first line is written in the coroutine the command itself runs in, the
 * second in a coroutine spawned from it - which is the shape nearly all real work has, a consumer
 * handling a message or an activity making a call, and the only one that proves the command is found by
 * walking up the parent chain rather than read out of the coroutine asking.
 *
 * Then it stays running, because a command that returns has its task worker recycled and the replacement
 * starts it over.
 */
#[AsCommand(
    name: 'test:worker-context:log',
    description: 'Long running command that logs one record per coroutine, for the worker context test.',
)]
final class WorkerContextLoggingCommand extends Command
{
    public const string MESSAGE_PREFIX = 'Worker context test';

    private bool $stopRequested = false;

    public function __construct(private readonly LoggerInterface $logger)
    {
        parent::__construct();
    }

    #[Override]
    public function getSubscribedSignals(): array
    {
        return defined('SIGTERM') ? [SIGTERM] : [];
    }

    #[Override]
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->stopRequested = true;

        return false;
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('marker', InputArgument::REQUIRED, 'Tells this instance from the others');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $marker */
        $marker = $input->getArgument('marker');

        // Warning rather than info: the fixture app's file handler accepts nothing below it, and a
        // record the handler drops would leave the test with nothing to read.
        $this->logger->warning(sprintf('%s: %s in the command.', self::MESSAGE_PREFIX, $marker));

        CoWrapper::go(function () use ($marker): void {
            $this->logger->warning(sprintf('%s: %s in a spawned coroutine.', self::MESSAGE_PREFIX, $marker));
        });

        while (!$this->stopRequested) {
            usleep(200000);
        }

        return self::SUCCESS;
    }
}
