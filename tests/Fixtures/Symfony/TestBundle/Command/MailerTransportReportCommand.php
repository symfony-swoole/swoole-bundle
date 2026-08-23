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
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

/**
 * Says what the container hands out for a mail transport, and whether two coroutines get one each.
 *
 * Neither fact can be had from outside. `debug:container` reports the definition as it stood before the
 * compile processors ran, and an application sending one mail at a time behaves identically either way
 * - which is the whole difficulty with this class of bug.
 *
 * The stream is what it asks about, rather than the transport, because the stream is the thing that
 * must not be shared: it holds the socket the SMTP dialogue is conducted over, and everything two
 * concurrent sends would tread on lives on it or behind it. Two coroutines holding two streams is two
 * connections, which is the fix stated in the terms of the fault.
 *
 * Nothing is ever sent and no connection is made: a transport is built and asked for its stream, both
 * of which happen without touching the network. So this needs no mail server, and the DSN below only
 * has to name one.
 */
#[AsCommand(
    name: 'test:mailer:transport-report',
    description: 'Reports how the container built the mail transport it was given.',
)]
final class MailerTransportReportCommand extends Command
{
    /**
     * Never connected to. Port 1 rather than something plausible, so that a change which starts opening
     * connections here fails loudly instead of quietly reaching somebody's local mail server.
     */
    private const string DSN = 'smtp://localhost:1';

    public function __construct(private readonly TransportFactoryInterface $factory)
    {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $transport = $this->factory->create(Dsn::fromString(self::DSN));

        $output->writeln(sprintf('pooled=%s', $transport instanceof ContextualProxy ? 'yes' : 'no'));
        $output->writeln(sprintf('class=%s', $transport::class));

        if (!$transport instanceof SmtpTransport) {
            $output->writeln('smtp=no');

            return self::SUCCESS;
        }

        $output->writeln('smtp=yes');

        // A rendezvous rather than a sleep, so the overlap is a fact rather than a hope - and because
        // the two engines disagree about sleeping: OpenSwoole's Coroutine::sleep() takes whole seconds
        // where Swoole's takes a float, and this fixture has to run on both.
        $here = new Channel(1);
        $there = new Channel(1);

        /** @var list<array{int, int}> $readings */
        $readings = CoroutinePool::fromCoroutines(
            fn(): array => $this->streamEitherSideOfTheOther($transport, $here, $there),
            fn(): array => $this->streamEitherSideOfTheOther($transport, $there, $here),
        )->run();

        $output->writeln(sprintf('coroutines=%d', count($readings)));
        $output->writeln(sprintf(
            'distinct_streams=%d',
            count(array_unique(array_merge(...array_map(array_values(...), $readings)))),
        ));
        $output->writeln(sprintf(
            'stable_within_coroutine=%s',
            array_filter($readings, static fn(array $pair): bool => $pair[0] !== $pair[1]) === [] ? 'yes' : 'no',
        ));

        return self::SUCCESS;
    }

    /**
     * The stream this coroutine is given, read before and after waiting for the other to arrive.
     *
     * Both readings, rather than one: a transport per coroutine is only worth anything if it is the
     * same transport for the whole of that coroutine's work. One reading each would also be satisfied
     * by a pool handing out a fresh transport on every call, which would open a connection per method
     * call and reuse nothing.
     *
     * The wait is what makes them overlap. Each coroutine announces itself on its own channel and then
     * blocks on the other's, so neither can finish before both have taken their first reading - which
     * is the only arrangement under which two coroutines really are holding a transport at once.
     *
     * @return array{int, int}
     */
    private function streamEitherSideOfTheOther(SmtpTransport $transport, Channel $here, Channel $there): array
    {
        $before = spl_object_id($transport->getStream());

        $here->push(true);
        $there->pop();

        return [$before, spl_object_id($transport->getStream())];
    }
}
