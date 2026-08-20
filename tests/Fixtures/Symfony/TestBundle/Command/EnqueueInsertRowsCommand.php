<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command;

use Override;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Message\InsertRow;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Fills the queue before the server that drains it is started.
 *
 * Sending from here rather than from the running server is what puts every consumer of a group under
 * load from its first poll: the whole batch is already waiting when they start, so none of them has to
 * be lucky about timing to get any of it.
 *
 * The ids are sequential rather than random, so a test reporting a missing or a duplicated message can
 * name it.
 */
#[AsCommand(
    name: 'test:messenger:enqueue',
    description: 'Sends InsertRow messages to the transport they are routed to.',
)]
final class EnqueueInsertRowsCommand extends Command
{
    public const string MESSAGE_ID_PREFIX = 'msg-';

    public function __construct(private readonly MessageBusInterface $messageBus)
    {
        parent::__construct();
    }

    public static function messageId(int $index): string
    {
        return sprintf('%s%04d', self::MESSAGE_ID_PREFIX, $index);
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('count', InputArgument::REQUIRED, 'How many messages to send')
            ->addOption('sleep-ms', null, InputOption::VALUE_REQUIRED, 'How long each handler sleeps', '0');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $count */
        $count = $input->getArgument('count');
        /** @var string $sleepMs */
        $sleepMs = $input->getOption('sleep-ms');

        for ($index = 1; $index <= (int) $count; $index++) {
            $this->messageBus->dispatch(new InsertRow(self::messageId($index), (int) $sleepMs));
        }

        $output->writeln(sprintf('Sent %d messages.', (int) $count));

        return self::SUCCESS;
    }
}
