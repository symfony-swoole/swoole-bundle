<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command;

use Override;
use Swoole\Coroutine\Channel;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use SwooleBundle\SwooleBundle\Coroutine\CoroutinePool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mercure\Debug\TraceableHub;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Says what the container hands out for a traced Mercure hub, and whether two coroutines publishing at once
 * each keep a trace of their own.
 *
 * The trace is what it asks about, because the trace is the state: TraceableHub appends every update it
 * publishes to one array, and that array is what two coroutines sharing the decorator write together. Each
 * coroutine therefore publishes, waits for the other to have published too, publishes again, and reads its
 * trace back. Pooled, that trace is exactly its own two updates. Shared, it holds the other coroutine's as
 * well, and the two traces come back three and four long.
 *
 * Nothing reaches a hub: the decorator wraps a NullHub, so this needs neither MercureBundle nor a Mercure
 * server.
 */
#[AsCommand(
    name: 'test:mercure:traceable-hub-report',
    description: 'Reports how the container built the traced Mercure hub it was given.',
)]
final class MercureTraceableHubReportCommand extends Command
{
    public function __construct(private readonly HubInterface $hub)
    {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('pooled=%s', $this->hub instanceof ContextualProxy ? 'yes' : 'no'));

        if (!$this->hub instanceof TraceableHub) {
            // The decoration did not take, so there is no trace to read and nothing below would prove anything.
            $output->writeln('traceable=no');

            return self::SUCCESS;
        }

        $output->writeln('traceable=yes');
        $hub = $this->hub;

        // A rendezvous rather than a sleep, so the overlap is a fact rather than a hope - see
        // MailerTransportReportCommand for why sleeping would not even behave the same on both engines.
        $here = new Channel(1);
        $there = new Channel(1);

        /** @var list<array{traced: int, own: bool}> $readings */
        $readings = CoroutinePool::fromCoroutines(
            fn(): array => $this->publishEitherSideOfTheOther($hub, 'first', $here, $there),
            fn(): array => $this->publishEitherSideOfTheOther($hub, 'second', $there, $here),
        )->run();

        $output->writeln(sprintf('coroutines=%d', count($readings)));
        $output->writeln(sprintf(
            'traced=%s',
            implode(',', array_map(static fn(array $reading): int => $reading['traced'], $readings)),
        ));
        $output->writeln(sprintf(
            'own_trace_only=%s',
            array_filter($readings, static fn(array $reading): bool => !$reading['own']) === [] ? 'yes' : 'no',
        ));

        return self::SUCCESS;
    }

    /**
     * Publishes once, waits for the other coroutine to have published, publishes again, and reads the trace.
     *
     * Twice rather than once, for the same reason the mailer report reads its stream twice: a hub per
     * coroutine is only right if it is the same hub for the whole of that coroutine's work. A pool handing
     * out a fresh decorator on every call would give one-update traces that look private and keep nothing.
     *
     * @return array{traced: int, own: bool}
     */
    private function publishEitherSideOfTheOther(
        TraceableHub $hub,
        string $topic,
        Channel $here,
        Channel $there,
    ): array {
        $before = new Update($topic, 'before');
        $hub->publish($before);

        $here->push(true);
        $there->pop();

        $after = new Update($topic, 'after');
        $hub->publish($after);

        $traced = array_column($hub->getMessages(), 'object');

        // Compared by identity: exactly this coroutine's two updates, in the order it published them.
        return ['traced' => count($traced), 'own' => $traced === [$before, $after]];
    }
}
