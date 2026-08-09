<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command;

use Override;
use ReflectionProperty;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use Symfony\Bundle\SecurityBundle\Security\FirewallContext;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Bundle\SecurityBundle\Security\LazyFirewallContext;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Firewall\ContextListener;

/**
 * Reports whether the stateful firewall's context and context listener were made worker-safe.
 *
 * Both are written for a container built fresh for one request and rewritten in place while serving one:
 * the profiler replaces a lazy context's listeners with timing wrappers, and the context listener notes
 * on itself that it has registered a response listener. Neither survives being shared by a worker - see
 * SecurityProcessor.
 */
#[AsCommand(
    name: 'test:security-firewall-context:proxy-check',
    description: 'Tells whether the firewall context is handed out fresh and its context listener pooled.',
)]
final class SecurityFirewallContextProxyCheckCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'security.firewall.map')]
        private readonly FirewallMap $firewallMap,
        // only exists with kernel.debug on - SecurityBundle wraps the authenticators in traceable
        // decorators nowhere else - so the environment wires it in as an optional reference and this
        // is null whenever the profiler is off
        private readonly ?AuthenticatorInterface $authenticator = null,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf(
            'requests %s the firewall context.',
            $this->contextIsSharedBetweenRequests() ? 'SHARE' : 'DO NOT SHARE',
        ));

        $contextListener = $this->contextListenerOfTheStatefulFirewall();

        if ($contextListener === null) {
            $output->writeln('context listener NOT FOUND.');

            return self::FAILURE;
        }

        $output->writeln(sprintf(
            'context listener %s proxified.',
            $contextListener instanceof ContextualProxy ? 'IS' : 'IS NOT',
        ));

        if ($this->authenticator === null) {
            $output->writeln('traceable authenticator IS ABSENT.');

            return self::SUCCESS;
        }

        $output->writeln(sprintf(
            'traceable authenticator %s proxified.',
            $this->authenticator instanceof ContextualProxy ? 'IS' : 'IS NOT',
        ));

        return self::SUCCESS;
    }

    /**
     * The map looks its context up in the container on every call, so asking twice is asking for the
     * object two requests would be handed. The same one back both times is the whole problem: the
     * profiler rewrites the listeners of whichever it is given, and every request after that reads what
     * the last one wrote.
     */
    private function contextIsSharedBetweenRequests(): bool
    {
        $first = $this->statefulFirewallContext();
        $second = $this->statefulFirewallContext();

        return $first !== null && $second !== null && spl_object_id($first) === spl_object_id($second);
    }

    private function statefulFirewallContext(): ?LazyFirewallContext
    {
        [$listeners] = $this->firewallMap->getListeners(Request::create('/security/session'));

        foreach ($listeners as $listener) {
            // a lazy context hands itself over as its own listener
            if ($listener instanceof LazyFirewallContext) {
                return $listener;
            }
        }

        return null;
    }

    /**
     * The listeners a lazy context holds are behind a private property with no accessor - getListeners()
     * on the context answers with the context itself.
     */
    private function contextListenerOfTheStatefulFirewall(): ?object
    {
        $context = $this->statefulFirewallContext();

        if ($context === null) {
            return null;
        }

        /** @var iterable<object> $listeners */
        $listeners = (new ReflectionProperty(FirewallContext::class, 'listeners'))->getValue($context);

        foreach ($listeners as $listener) {
            $unwrapped = $listener instanceof ContextualProxy ? $listener->getContextualObject() : $listener;

            if ($unwrapped instanceof ContextListener) {
                return $listener;
            }
        }

        return null;
    }
}
