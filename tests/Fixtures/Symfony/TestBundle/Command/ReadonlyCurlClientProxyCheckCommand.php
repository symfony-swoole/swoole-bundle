<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command;

use Override;
use ReflectionClass;
use Swoole\Coroutine\Channel;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use SwooleBundle\SwooleBundle\Coroutine\CoroutinePool;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\ReadonlyCurlClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Uses the readonly curl client from two coroutines at once, and says which handle each of them drove.
 *
 * Each coroutine asks for its handle twice, and between the two it meets the other one - a rendezvous on
 * a pair of channels - so both are holding the client at the same moment. That overlap is what makes the
 * answer mean something: a pool hands an instance back when a coroutine releases it, so two coroutines
 * that ran one after the other could get the same instance and still be correct. Two that overlap must
 * not. A rendezvous rather than a sleep also because the engines disagree about sleeping: OpenSwoole's
 * Coroutine::sleep() takes whole seconds, Swoole's a float.
 */
#[AsCommand(
    name: 'test:readonly-curl-client:proxy-check',
    description: 'Tells whether the readonly curl client is proxified, and which handle each coroutine uses.',
)]
final class ReadonlyCurlClientProxyCheckCommand extends Command
{
    public function __construct(private readonly ReadonlyCurlClient $client)
    {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf(
            'Client %s proxified.',
            self::isProxified($this->client) ? 'IS' : 'IS NOT',
        ));
        $output->writeln(sprintf(
            'Client class is %s.',
            (new ReflectionClass($this->client))->isReadOnly() ? 'readonly' : 'not readonly',
        ));
        $output->writeln(sprintf('Base URI: %s', $this->client->baseUri));

        $here = new Channel(1);
        $there = new Channel(1);

        /** @var array{string, string} $handles */
        $handles = CoroutinePool::fromCoroutines(
            fn(): string => $this->useTheClientEitherSideOfTheOther($here, $there),
            fn(): string => $this->useTheClientEitherSideOfTheOther($there, $here),
        )->run();
        [$first, $second] = $handles;

        $output->writeln(sprintf('Coroutine A handles: %s', $first));
        $output->writeln(sprintf('Coroutine B handles: %s', $second));

        return self::SUCCESS;
    }

    /**
     * Asked of an object rather than of the client's own type: the client is final, so statically it can
     * never be a proxy - z-engine is what removes `final`, at runtime, before the proxy extends it.
     */
    private static function isProxified(object $service): bool
    {
        return $service instanceof ContextualProxy;
    }

    private function useTheClientEitherSideOfTheOther(Channel $here, Channel $there): string
    {
        $before = $this->client->handleId();

        $here->push(true);
        $there->pop();

        return sprintf('%d %d', $before, $this->client->handleId());
    }
}
