<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\TaskWorker;

use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Records the signals handed to it, standing in for a command that winds itself down on one.
 */
final class SignalRecordingCommand extends Command
{
    private bool $executed = false;

    /**
     * @var list<int>
     */
    private array $handled = [];

    /**
     * @param list<int> $signals
     */
    public function __construct(private readonly array $signals)
    {
        parent::__construct('app:noop');
    }

    public function wasExecuted(): bool
    {
        return $this->executed;
    }

    /**
     * @return list<int>
     */
    public function handledSignals(): array
    {
        return $this->handled;
    }

    #[Override]
    public function getSubscribedSignals(): array
    {
        return $this->signals;
    }

    #[Override]
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->handled[] = $signal;

        return false;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->executed = true;

        return self::SUCCESS;
    }
}
