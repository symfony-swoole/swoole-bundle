<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command;

use Override;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Says what the container actually hands out for a transport.
 *
 * The two facts a test cannot get at any other way. Whether the transport is pooled is invisible from
 * outside - `debug:container` reports the definition as it was before the compile processors ran, and
 * the consumers themselves behave the same either way until something goes wrong. And whether the
 * pooled proxy still answers to the capability interfaces is the part that fails quietly: messenger
 * finds each of them with an instanceof, so a proxy generated from the declared TransportInterface
 * would leave `messenger:setup-transports` skipping the transport without a word.
 */
#[AsCommand(
    name: 'test:messenger:transport-report',
    description: 'Reports how the container built the messenger transport it was given.',
)]
final class MessengerTransportReportCommand extends Command
{
    public function __construct(private readonly TransportInterface $transport)
    {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $facts = [
            'pooled' => $this->transport instanceof ContextualProxy,
            'setupable' => $this->transport instanceof SetupableTransportInterface,
            'countable' => $this->transport instanceof MessageCountAwareInterface,
        ];

        foreach ($facts as $name => $holds) {
            $output->writeln(sprintf('%s=%s', $name, $holds ? 'yes' : 'no'));
        }

        $output->writeln(sprintf('class=%s', $this->transport::class));

        return self::SUCCESS;
    }
}
