<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command;

use Override;
use stdClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Fixture double for what Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension registers per
 * firewall (security.event_dispatcher.<name>, optionally decorated by a TraceableEventDispatcher — see
 * MakeFirewallsEventDispatcherTraceablePass). Used to prove EventDispatcherProcessor makes each
 * firewall-scoped dispatcher coroutine-safe without pulling in symfony/security-bundle as a real
 * dependency of this test suite.
 */
#[AsCommand(
    name: 'test:security-event-dispatcher:proxy-check',
    description: 'Tells whether each firewall-scoped event dispatcher has been proxified and still works.',
)]
final class SecurityFirewallEventDispatcherProxyCheckCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'security.event_dispatcher.main')]
        private readonly EventDispatcherInterface $mainDispatcher,
        #[Autowire(service: 'security.event_dispatcher.api')]
        private readonly EventDispatcherInterface $apiDispatcher,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (['main' => $this->mainDispatcher, 'api' => $this->apiDispatcher] as $firewallName => $dispatcher) {
            $isProxified = $dispatcher instanceof ContextualProxy ? 'IS' : 'IS NOT';
            $output->writeln(sprintf('%s %s proxified.', $firewallName, $isProxified));

            $event = new stdClass();
            $dispatcher->addListener(
                'test.firewall_event',
                static function (stdClass $event) use ($firewallName): void {
                    $event->firewallName = $firewallName;
                },
            );
            $dispatcher->dispatch($event, 'test.firewall_event');

            $output->writeln(sprintf(
                '%s dispatch %s.',
                $firewallName,
                ($event->firewallName ?? null) === $firewallName ? 'WORKS' : 'FAILED',
            ));
        }

        return self::SUCCESS;
    }
}
