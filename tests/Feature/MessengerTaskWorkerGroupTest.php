<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Doctrine\DBAL\Connection;
use Override;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command\EnqueueInsertRowsCommand;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;
use SwooleBundle\SwooleBundle\Tests\Helper\TestToken;
use Symfony\Component\Process\Process;

/**
 * EXPERIMENTAL feature - a group of messenger consumers running inside one task worker.
 *
 * Four consumers, one process, one transport over one queue: the shape an application reaches when it
 * moves its consumers out of their own containers and into the server. What is under test is not that
 * a consumer runs - TaskWorkerCommandsTest covers that with a command that only ticks - but that four
 * of them doing real work at once handle every message exactly once, and give back what they had not
 * reached when the server is stopped underneath them.
 *
 * @see docs/swoole-task-worker-commands.md
 */
final class MessengerTaskWorkerGroupTest extends ServerTestCase
{
    private const string ENVIRONMENT = 'task_worker_messenger';

    /**
     * The one transport all four consumers of the group receive through - see the environment's
     * messenger.php for why sharing it is only safe because the bundle pools it.
     */
    private const string TRANSPORT = 'default';

    private const int CONSUMER_COUNT = 4;

    private const int MESSAGE_COUNT = 200;

    /**
     * The batch the group is stopped in the middle of - big enough that it is still draining seconds
     * after the stop is asked for.
     */
    private const int STOP_MESSAGE_COUNT = 1000;

    /**
     * How much of that batch has to be handled before the server is stopped. Only enough to know the
     * group is working rather than still waking up - every message handled past this one is margin
     * taken off the assertion that the stop landed mid-batch.
     */
    private const int STOP_AFTER_HANDLED = 20;

    /**
     * What one message costs the consumer that takes it, in the batch that is stopped mid-flight.
     *
     * Larger than the drained batch's, and it costs nothing: that batch is never finished, so this only
     * decides how much of it is left when the stop lands. Four consumers at 25ms need something over
     * six seconds for the whole thousand, against the fraction of a second between the twentieth
     * message and the signal.
     */
    private const int STOP_HANDLER_SLEEP_MS = 25;

    /**
     * Long enough that a consumer cannot drain the queue between its siblings' polls, short enough that
     * the drained batch is under two seconds of work - four hundred milliseconds apiece.
     */
    private const int HANDLER_SLEEP_MS = 8;

    /**
     * How long the acks of the last messages handled are given to land before what is still on the queue
     * is taken to be what the shutdown left behind. Scaled with the parallel suite's timeout factor,
     * since it is the loaded box that stretches the gap this covers.
     */
    private const float SETTLE_SECONDS = 1.0;

    /**
     * How long a stop is given before Process::stop() gives up and sends SIGKILL. Ten seconds is its own
     * default and is what one server on an idle machine needs; a group of four consumers finishing their
     * messages on a box running the whole suite at once needs the same room the other waits here get,
     * and a server killed halfway through its shutdown is exactly what this test is looking for.
     */
    private const float STOP_TIMEOUT_SECONDS = 10.0;

    private const int STARTUP_TIMEOUT_SECONDS = 60;

    private const int DRAIN_TIMEOUT_SECONDS = 60;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    /**
     * That the transport the group shares is pooled, and is still the transport it was.
     *
     * Asked of the container rather than inferred from a run, because neither half shows in one. Four
     * consumers sharing a single transport instance drain a queue correctly most of the time - what
     * they corrupt is the transport's own bookkeeping, which surfaces later and elsewhere - so the
     * exactly-once test below passes whether or not the pooling happened. And the capability
     * interfaces fail silently by design: messenger finds each of them with an instanceof, so a
     * transport pooled from its declared TransportInterface would leave `messenger:setup-transports`
     * skipping it without a word.
     *
     * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\MessengerProcessor
     */
    public function testTheSharedTransportIsPooledWithoutLosingItsCapabilities(): void
    {
        $report = $this->runConsole(['test:messenger:transport-report'], ['APP_ENV' => self::ENVIRONMENT]);
        $output = $report->getOutput();

        self::assertStringContainsString('pooled=yes', $output, sprintf(
            'The transport was left shared, so the whole group receives through one instance. %s',
            trim($output),
        ));
        self::assertStringContainsString('setupable=yes', $output, sprintf(
            'The pooled transport is no longer setupable, so it was proxied from the wrong class. %s',
            trim($output),
        ));
        self::assertStringContainsString('countable=yes', $output, sprintf(
            'The pooled transport can no longer be counted, so it was proxied from the wrong class. %s',
            trim($output),
        ));
    }

    public function testAGroupOfConsumersDrainsOneQueueExactlyOnce(): void
    {
        $envs = ['APP_ENV' => self::ENVIRONMENT];

        $this->prepareDatabase($envs);

        $serverRun = $this->startServerWithConsumers($envs);

        $this->enqueueMessages($envs, self::MESSAGE_COUNT);

        $handled = $this->awaitHandledCount(self::MESSAGE_COUNT);
        // Waited for separately, and before the server is stopped, because the row a handler writes is
        // committed before the message it was handling is acked. The last message can therefore be
        // counted as handled while its ack is still on its way, and a server stopped in that instant
        // leaves the row on the queue for a redelivery that never comes - a race in the reading of it
        // rather than in the draining.
        $stillQueued = $this->awaitQueueDrained();

        $this->stopServer($serverRun);

        self::assertSame(
            [],
            $this->failedMessages(),
            'A handler threw. The messages are on the failure transport with the reason.',
        );

        self::assertSame(
            self::MESSAGE_COUNT,
            $handled,
            sprintf('Not every message was handled. %s', $this->handlingReport(self::MESSAGE_COUNT)),
        );
        self::assertSame(
            self::MESSAGE_COUNT,
            $this->distinctCount('message_id'),
            sprintf('A message was handled more than once. %s', $this->handlingReport(self::MESSAGE_COUNT)),
        );
        self::assertSame(
            0,
            $stillQueued,
            'The queue was left with messages on it, so an ack never landed and they would be handled '
            . 'again by whatever consumes next.',
        );

        // The consumers are coroutines of one process, so this is what "four workers, not one doing all
        // the work" looks like from the outside: four coroutine ids across the rows, one pid.
        self::assertSame(
            self::CONSUMER_COUNT,
            $this->distinctCount('coroutine_id'),
            sprintf(
                'Not every consumer of the group handled a message. %s',
                $this->handlingReport(self::MESSAGE_COUNT),
            ),
        );
        self::assertSame(
            1,
            $this->distinctCount('worker_pid'),
            'The consumers of one group must all run in the same task worker process.',
        );
    }

    /**
     * The other half of the workload: what the group does when it is asked to stop with work in flight.
     *
     * A consumer is stopped between messages, so the message being handled when the server goes down is
     * finished and everything the group had not reached is still on the queue for the next process.
     * What is checked is that nothing fell between the two: every message sent is either handled or
     * still queued.
     *
     * Not exactly one or the other, though - messenger delivers at least once, and the ack of a message
     * that was being handled as the worker went away need not have landed, which leaves it both handled
     * and queued for redelivery. That is correct behaviour rather than a fault, and it is bounded: a
     * consumer holds one message at a time, so at most one per consumer can be in that state - counted
     * over the consumers that ran rather than the four of one group, since a task worker that is
     * replaced leaves the replacement's four able to be holding one each as well.
     *
     * The row itself says which of the two a message in both places is. Still marked delivered, and it
     * is the row a consumer was holding. Not marked, and it is a second row for a message already
     * finished - the message was handed back rather than left in flight, which no shutdown excuses
     *
     * Four consumers stopping at once is also where the stop path itself is under the most pressure:
     * every one of them acknowledges the stop and finishes from a coroutine of its own, and the wait
     * group the task worker recycles behind is touched by each of them in turn.
     */
    public function testTheGroupStopsMidFlightWithoutLosingMessages(): void
    {
        $envs = ['APP_ENV' => self::ENVIRONMENT];

        $this->prepareDatabase($envs);

        // Filled before the consumers exist, unlike the drained batch, which has to be sent to a group
        // already polling so that every consumer gets a fair share of it. Nothing here counts what each
        // consumer did, and sending first is what guarantees there is a backlog to be stopped in the
        // middle of: sent afterwards, the four of them drain it as fast as one console process can
        // dispatch it, and the stop arrives to find nothing left.
        $this->enqueueMessages($envs, self::STOP_MESSAGE_COUNT, self::STOP_HANDLER_SLEEP_MS);

        $serverRun = $this->startServerWithConsumers($envs);

        $handledBeforeStop = $this->awaitHandledCount(self::STOP_AFTER_HANDLED);

        self::assertGreaterThanOrEqual(
            self::STOP_AFTER_HANDLED,
            $handledBeforeStop,
            'The group never got going, so this stopped nothing mid-flight.',
        );

        $this->stopServer($serverRun);

        $output = $serverRun->getOutput() . $serverRun->getErrorOutput();

        // The server saying it shut down is what tells a stop apart from a force-termination at
        // max_wait_time, which is how a group that could not be asked to stop ends instead.
        self::assertStringContainsString(
            'Swoole HTTP Server has been successfully shutdown',
            $output,
            'The server did not shut down cleanly, so the consumers were force-terminated.',
        );

        // Read back to back, and before the assertions that query for themselves: a message handled and
        // acked between the two reads is in neither count, and the pair is what "nothing was lost" is
        // decided on below.
        $handled = $this->handledCount();
        $stillQueued = $this->queueDepth(self::TRANSPORT);

        self::assertLessThan(
            self::STOP_MESSAGE_COUNT,
            $handled,
            'The batch was drained before the stop, so nothing about stopping mid-flight was tested.',
        );
        self::assertSame(
            $handled,
            $this->distinctCount('message_id'),
            sprintf('A message was handled more than once. %s', $this->handlingReport(self::STOP_MESSAGE_COUNT)),
        );
        self::assertSame(
            [],
            $this->failedMessages(),
            'A handler threw. The messages are on the failure transport with the reason.',
        );
        $accountedFor = $handled + $stillQueued;

        self::assertGreaterThanOrEqual(
            self::STOP_MESSAGE_COUNT,
            $accountedFor,
            sprintf(
                'Handled %d and left %d on the queue, out of %d sent - a message the shutdown neither '
                . 'finished nor gave back.',
                $handled,
                $stillQueued,
                self::STOP_MESSAGE_COUNT,
            ),
        );
        // Measured rather than inferred from the sum: which messages are in both places is what the
        // bound below is about, and the two of them fail with different things to say.
        $handledButQueued = $this->settledHandledButStillQueued();

        // A row nobody is holding - delivered_at null - carrying a message that was handled is a message
        // put back after it was finished, which is the fault this test is named for. No shutdown
        // excuses it, so the bound for these is none at all.
        self::assertSame(
            [],
            array_values(array_filter(
                $handledButQueued,
                static fn(array $row): bool => $row['delivered_at'] === null,
            )),
            sprintf(
                'A message was put back on the queue after it had been handled. Handled %d and left %d '
                . 'on the queue, out of %d sent.',
                $handled,
                $stillQueued,
                self::STOP_MESSAGE_COUNT,
            ),
        );

        // What is left is a message a consumer was still holding when its process went away, and a
        // consumer holds one at a time - so the bound is one per consumer that ever ran. That is the
        // four of the group for a run the group lived through, and four more for every further task
        // worker the run went through, which is what the distinct pids count: a group whose commands
        // end is replaced, and the replacement's consumers are four more that can each be holding one.
        $consumersThatRan = $this->distinctCount('worker_pid') * self::CONSUMER_COUNT;

        self::assertLessThanOrEqual(
            $consumersThatRan,
            count($handledButQueued),
            sprintf(
                'Handled %d and left %d on the queue, out of %d sent - more than the one message per '
                . 'consumer that can be in flight was given back after being handled. Both handled and '
                . 'queued: %s.',
                $handled,
                $stillQueued,
                self::STOP_MESSAGE_COUNT,
                implode(', ', $this->describeRows($handledButQueued)),
            ),
        );
    }

    /**
     * Starts the server and waits until the whole group is polling.
     *
     * Nothing is sent before that. Filled beforehand, the queue is drained by whichever consumers boot
     * first while the rest are still resolving their command line out of the container, and how many of
     * them took part in the test comes down to how warm the caches happened to be.
     *
     * @param array<string, string> $envs
     */
    private function startServerWithConsumers(array $envs): Process
    {
        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], $envs);

        $serverRun->setTimeout(120);
        $serverRun->start();

        $this->runAsCoroutineAndWait(function (): void {
            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(), 1, true));
        });

        $this->awaitConsumersReady($serverRun);

        return $serverRun;
    }

    /**
     * Stops the server, with the shutdown given as long as every other wait here is.
     */
    private function stopServer(Process $serverRun): void
    {
        $serverRun->stop(self::STOP_TIMEOUT_SECONDS * TestToken::timeoutFactor());
    }

    /**
     * Waits until every consumer of the group has said it is consuming.
     *
     * They announce themselves as they start, so this is the moment the group is fully up - and there
     * is no other way to know it from outside: the task worker holds them, and the server answers
     * requests from its http worker whether or not the consumers ever got going.
     */
    private function awaitConsumersReady(Process $serverRun): void
    {
        $deadline = microtime(true) + self::STARTUP_TIMEOUT_SECONDS;
        $announcement = sprintf('Consuming messages from transport "%s".', self::TRANSPORT);

        while (true) {
            $output = $serverRun->getOutput() . $serverRun->getErrorOutput();
            // Counted rather than matched by name: every consumer of this group receives through the
            // same transport, so they all announce themselves with the same line.
            $started = substr_count($output, $announcement);

            if ($started >= self::CONSUMER_COUNT) {
                return;
            }

            if (microtime(true) >= $deadline) {
                self::fail(sprintf(
                    'Only %d of %d consumers started. Server output: %s',
                    $started,
                    self::CONSUMER_COUNT,
                    $output,
                ));
            }

            usleep(100_000);
        }
    }

    /**
     * @param array<string, string> $envs
     */
    private function prepareDatabase(array $envs): void
    {
        $this->runConsole(['cache:clear'], $envs);
        $this->runConsole(['doctrine:schema:drop', '--full-database', '--force'], $envs);
        $this->runConsole(['doctrine:migrations:migrate', '--no-interaction'], $envs);
        // Explicitly, rather than left to the transports to do for themselves on first use: they all
        // name the same table, and four consumers creating it at once is a race of the test's own
        // making rather than one worth reporting.
        $this->runConsole(['messenger:setup-transports'], $envs);
    }

    /**
     * @param array<string, string> $envs
     */
    private function enqueueMessages(array $envs, int $count, int $sleepMs = self::HANDLER_SLEEP_MS): void
    {
        $this->runConsole([
            'test:messenger:enqueue',
            (string) $count,
            sprintf('--sleep-ms=%d', $sleepMs),
        ], $envs);
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

    /**
     * Waits for the group to drain the queue, and gives up rather than hanging if it does not.
     *
     * Returns whatever had been handled when it stopped waiting, so that a run which handled 197 of 200
     * fails on the count with the number it reached instead of on a timeout.
     */
    private function awaitHandledCount(int $expected): int
    {
        $deadline = microtime(true) + self::DRAIN_TIMEOUT_SECONDS;

        while (true) {
            $handled = $this->handledCount();

            if ($handled >= $expected || microtime(true) >= $deadline) {
                return $handled;
            }

            usleep(50_000);
        }
    }

    /**
     * Waits for the acks to land, and gives up rather than hanging if they do not.
     *
     * Returns the depth it stopped waiting at, so a queue that never empties fails on what was left
     * rather than on a timeout.
     */
    private function awaitQueueDrained(): int
    {
        $deadline = microtime(true) + self::DRAIN_TIMEOUT_SECONDS;

        while (true) {
            $depth = $this->queueDepth(self::TRANSPORT);

            if ($depth === 0 || microtime(true) >= $deadline) {
                return $depth;
            }

            usleep(50_000);
        }
    }

    private function handledCount(): int
    {
        return (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM consumed_message');
    }

    private function distinctCount(string $column): int
    {
        return (int) $this->connection()->fetchOne(
            sprintf('SELECT COUNT(DISTINCT %s) FROM consumed_message', $column),
        );
    }

    private function queueDepth(string $queueName): int
    {
        return (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM messenger_messages WHERE queue_name = ?',
            [$queueName],
        );
    }

    /**
     * What is on the failure transport, as the reason each message ended up there.
     *
     * The reason travels in the stamp headers rather than in a column of its own, so this reads the
     * whole header blob back - it is only ever looked at when the test is already failing, and a
     * truncated exception message is worth more than a row count.
     *
     * @return list<string>
     */
    private function failedMessages(): array
    {
        $rows = $this->connection()->fetchFirstColumn(
            'SELECT headers FROM messenger_messages WHERE queue_name = ? LIMIT 5',
            ['failed'],
        );

        return array_map(static fn(mixed $headers): string => mb_substr((string) $headers, 0, 500), $rows);
    }

    /**
     * The messages that are both handled and still on the queue, read once the counts have settled.
     *
     * A stop is read from outside the server, and a consumer that finished a message a moment before
     * the reading has its ack still on the way - which shows here as a message in both places and is
     * gone a moment later. So a run with anything in both is read a second time, and only what is still
     * there is what the shutdown really left behind. Costs a wait on the unhappy path alone.
     *
     * @return list<array{message_id: string, coroutine_id: int, worker_pid: int, delivered_at: ?string}>
     */
    private function settledHandledButStillQueued(): array
    {
        $rows = $this->handledButStillQueued();

        if ($rows === []) {
            return $rows;
        }

        usleep((int) (self::SETTLE_SECONDS * TestToken::timeoutFactor() * 1_000_000));

        return $this->handledButStillQueued();
    }

    /**
     * The join is a LIKE over the serialized body, which is where the message id is - the ids are fixed
     * width, so one cannot be a prefix of another.
     *
     * @return list<array{message_id: string, coroutine_id: int, worker_pid: int, delivered_at: ?string}>
     */
    private function handledButStillQueued(): array
    {
        /** @var list<array{message_id: string, coroutine_id: int, worker_pid: int, delivered_at: ?string}> $rows */
        $rows = $this->connection()->fetchAllAssociative(
            'SELECT c.message_id, c.coroutine_id, c.worker_pid, m.delivered_at FROM consumed_message c '
            . 'JOIN messenger_messages m ON m.body LIKE CONCAT(\'%\', c.message_id, \'%\') '
            . 'WHERE m.queue_name = ? ORDER BY c.message_id',
            [self::TRANSPORT],
        );

        return $rows;
    }

    /**
     * Names the rows for a failure message.
     *
     * delivered_at separates the two ways a handled message can still be on the queue: still set means
     * the consumer was holding it when its process went away, and null means this is a different row
     * from the one that was handled - the message was put back.
     *
     * @param list<array{message_id: string, coroutine_id: int, worker_pid: int, delivered_at: ?string}> $rows
     * @return list<string>
     */
    private function describeRows(array $rows): array
    {
        return array_map(
            static fn(array $row): string => sprintf(
                '%s (consumer #%d, pid %d, %s)',
                $row['message_id'],
                $row['coroutine_id'],
                $row['worker_pid'],
                $row['delivered_at'] === null ? 'put back' : 'in delivery since ' . $row['delivered_at'],
            ),
            $rows,
        );
    }

    /**
     * Names what went missing or arrived twice, for a failure message.
     *
     * $sentCount is what was sent, since "missing" only means anything against that.
     */
    private function handlingReport(int $sentCount): string
    {
        $connection = $this->connection();

        /** @var list<string> $duplicated */
        $duplicated = $connection->fetchFirstColumn(
            'SELECT message_id FROM consumed_message GROUP BY message_id HAVING COUNT(*) > 1 LIMIT 5',
        );

        /** @var list<string> $handledIds */
        $handledIds = $connection->fetchFirstColumn('SELECT DISTINCT message_id FROM consumed_message');
        $missing = [];

        for ($index = 1; $index <= $sentCount && count($missing) < 5; $index++) {
            $messageId = EnqueueInsertRowsCommand::messageId($index);

            if (in_array($messageId, $handledIds, true)) {
                continue;
            }

            $missing[] = $messageId;
        }

        /** @var list<array{coroutine_id: int, handled: int}> $perConsumer */
        $perConsumer = $connection->fetchAllAssociative(
            'SELECT coroutine_id, COUNT(*) AS handled FROM consumed_message GROUP BY coroutine_id',
        );

        return sprintf(
            'Missing: %s. Duplicated: %s. Per consumer: %s.',
            $missing === [] ? 'none' : implode(', ', $missing),
            $duplicated === [] ? 'none' : implode(', ', $duplicated),
            implode(', ', array_map(
                static fn(array $row): string => sprintf('#%d handled %d', $row['coroutine_id'], $row['handled']),
                $perConsumer,
            )),
        );
    }

    private function connection(): Connection
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        return $connection;
    }
}
