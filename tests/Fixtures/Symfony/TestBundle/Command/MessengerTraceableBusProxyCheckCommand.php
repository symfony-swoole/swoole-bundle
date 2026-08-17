<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command;

use Override;
use ReflectionProperty;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\DataCollector\MessengerDataCollector;

/**
 * Reports whether the traceable buses reached through the messenger data collector are pooled.
 *
 * The collector is what holds them, and it holds whatever MessengerPass put in its registerBus() call
 * at compile time - so asking the container for the bus proves nothing about what the collector will
 * write to on its way out of a request. This asks the collector.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger\MessengerProcessor
 */
#[AsCommand(
    name: 'test:messenger:traceable-bus-proxy-check',
    description: 'Tells whether the traceable buses held by the messenger data collector are proxified.',
)]
final class MessengerTraceableBusProxyCheckCommand extends Command
{
    public function __construct(private readonly MessengerDataCollector $dataCollector)
    {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $buses = $this->registeredBuses();

        $output->writeln(sprintf('Buses registered: %d', count($buses)));

        foreach ($buses as $busName => $bus) {
            $isProxified = $bus instanceof ContextualProxy ? 'IS' : 'IS NOT';

            $output->writeln(sprintf('Bus %s %s proxified.', $busName, $isProxified));
        }

        // Resetting is what the pool does to the collector on the way out of every coroutine, and where
        // the cross-coroutine write on a shared bus showed up. Reaching it here proves nothing about
        // concurrency, only that the pooled bus is still reachable and resettable through the proxy.
        $this->dataCollector->reset();

        $output->writeln('Collector reset.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, object>
     */
    private function registeredBuses(): array
    {
        $property = new ReflectionProperty(MessengerDataCollector::class, 'traceableBuses');

        /** @var array<string, object> $buses */
        $buses = $property->getValue($this->dataCollector);

        return $buses;
    }
}
