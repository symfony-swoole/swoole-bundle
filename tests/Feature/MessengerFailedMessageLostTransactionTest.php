<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Doctrine\DBAL\Connection;
use Override;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;
use Symfony\Component\Process\Process;

/**
 * A message whose handler leaves its pooled connection inside a transaction the server has lost.
 *
 * That is what a deadlock inside a nested transaction does on MySQL: the server rolls the whole transaction
 * back, savepoints included, DBAL's rollback to the savepoint fails before its nesting level is lowered, and
 * the connection keeps believing it is two levels deep. A consumer holds the one connection its coroutine was
 * given for the whole of its run, and the Doctrine transport sends the failed message on, to the failure
 * transport here, through that same connection. Before ConnectionStabilityChecker, and the reset the worker
 * now runs as soon as a message fails, that send failed as well, the consumer died with the message stuck
 * on the queue, and every message behind it was left for whoever consumed next.
 *
 * The failure is made by hand (LeaveConnectionInLostTransactionHandler), because a deadlock is timing and
 * cannot be asked for; the connection it leaves is the one a real deadlock leaves.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Doctrine\DBAL\ConnectionStabilityChecker
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\ResetServicePoolsBetweenMessages
 */
final class MessengerFailedMessageLostTransactionTest extends ServerTestCase
{
    /**
     * Doctrine transport on MySQL, a failure transport and no retries, under coroutines - see its
     * messenger.php and swoole.php.
     */
    private const string ENVIRONMENT = 'task_worker_messenger';

    private const int MESSAGE_COUNT = 3;

    /**
     * @see MessengerTaskWorkerGroupTest::ACKNOWLEDGED_ROW
     */
    private const string ACKNOWLEDGED_ROW = '9999-12-31 23:59:59';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testAMessageThatLeftItsConnectionInALostTransactionFailsAloneAndTheConsumerCarriesOn(): void
    {
        $envs = ['APP_ENV' => self::ENVIRONMENT];
        $this->prepareDatabase($envs);
        $this->runConsole(
            ['test:messenger:enqueue', (string) self::MESSAGE_COUNT, '--break-connection-first'],
            $envs,
        );

        // The broken message and the batch behind it: one consumer, stopping once it has been through all
        // of them - or on the time limit, rather than hanging, if it stops handling anything.
        $consume = $this->createConsoleProcess(
            [
                'messenger:consume',
                'default',
                sprintf('--limit=%d', self::MESSAGE_COUNT + 1),
                '--time-limit=30',
                '--sleep=0.1',
            ],
            $envs,
        );
        $consume->setTimeout(60);
        $consume->run();

        self::assertTrue($consume->isSuccessful(), sprintf(
            'The consumer died instead of moving on from the failed message. %s',
            $this->processReport($consume),
        ));
        self::assertCount(
            1,
            $this->failedMessages(),
            sprintf(
                'The failed message did not reach the failure transport. %s',
                $this->processReport($consume),
            ),
        );
        self::assertSame(
            self::MESSAGE_COUNT,
            $this->handledCount(),
            sprintf(
                'The messages behind the failed one were not all handled. %s',
                $this->processReport($consume),
            ),
        );
        self::assertSame(0, $this->queueDepth('default'), 'Messages were left on the queue.');
        self::assertStringContainsString(
            'still inside a transaction (nesting level 2)',
            $consume->getErrorOutput() . $consume->getOutput(),
            'The broken connection was never dropped, so this passed without the fix having run.',
        );
    }

    /**
     * @param array<string, string> $envs
     */
    private function prepareDatabase(array $envs): void
    {
        $this->runConsole(['cache:clear'], $envs);
        $this->runConsole(['doctrine:schema:drop', '--full-database', '--force'], $envs);
        $this->runConsole(['doctrine:migrations:migrate', '--no-interaction'], $envs);
        $this->runConsole(['messenger:setup-transports'], $envs);
    }

    /**
     * @param array<string> $args
     * @param array<string, string> $envs
     */
    private function runConsole(array $args, array $envs): Process
    {
        $process = $this->createConsoleProcess($args, $envs);
        $process->setTimeout(60);
        $process->run();

        $this->assertProcessSucceeded($process);

        return $process;
    }

    private function handledCount(): int
    {
        return (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM consumed_message');
    }

    private function queueDepth(string $queueName): int
    {
        return (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM messenger_messages WHERE queue_name = ? AND '
                . '(delivered_at IS NULL OR delivered_at <> ?)',
            [$queueName, self::ACKNOWLEDGED_ROW],
        );
    }

    /**
     * @return list<string>
     */
    private function failedMessages(): array
    {
        $rows = $this->connection()->fetchFirstColumn(
            'SELECT headers FROM messenger_messages WHERE queue_name = ? AND '
                . '(delivered_at IS NULL OR delivered_at <> ?)',
            ['failed', self::ACKNOWLEDGED_ROW],
        );

        return array_map(static fn(mixed $headers): string => mb_substr((string) $headers, 0, 300), $rows);
    }

    private function processReport(Process $process): string
    {
        return sprintf(
            'Exit code %s. Output: %s Error output: %s',
            var_export($process->getExitCode(), true),
            mb_substr(trim($process->getOutput()), -1500),
            mb_substr(trim($process->getErrorOutput()), -1500),
        );
    }

    private function connection(): Connection
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        return $connection;
    }
}
