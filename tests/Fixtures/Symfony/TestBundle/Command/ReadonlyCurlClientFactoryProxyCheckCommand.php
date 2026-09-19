<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command;

use Override;
use ProxyManager\Proxy\AccessInterceptorValueHolderInterface;
use ReflectionClass;
use Swoole\Coroutine\Channel;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use SwooleBundle\SwooleBundle\Coroutine\CoroutinePool;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\ReadonlyCurlClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\ReadonlyCurlClientFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Takes one client from the readonly factory and uses it from two coroutines at once, saying which curl
 * handle each of them drove.
 *
 * One client rather than one each, because that is the case the pool is for: a client held by a
 * service, reached by every coroutine that service is used from. Each coroutine meets the other between
 * its two calls - a rendezvous on a pair of channels - so both hold the client at the same moment, which
 * is when two coroutines must not be driving the same handle.
 */
#[AsCommand(
    name: 'test:readonly-curl-client-factory:proxy-check',
    description: 'Tells whether the readonly curl client factory is wrapped, and which handle each coroutine uses.',
)]
final class ReadonlyCurlClientFactoryProxyCheckCommand extends Command
{
    public function __construct(private readonly ReadonlyCurlClientFactory $factory)
    {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf(
            'Factory %s wrapped.',
            self::isA($this->factory, AccessInterceptorValueHolderInterface::class) ? 'IS' : 'IS NOT',
        ));
        $output->writeln(sprintf('Factory class is %s.', self::readonlyOrNot($this->factory)));
        $output->writeln(sprintf('Factory base URI: %s', $this->factory->baseUri));

        // The factory's interceptor locks with coroutine mutexes, so the client is asked for from one.
        /** @var array{ReadonlyCurlClient} $created */
        $created = CoroutinePool::fromCoroutines(fn(): ReadonlyCurlClient => $this->factory->create())->run();
        [$client] = $created;

        $output->writeln(sprintf('Client %s proxified.', self::isA($client, ContextualProxy::class) ? 'IS' : 'IS NOT'));
        $output->writeln(sprintf('Client class is %s.', self::readonlyOrNot($client)));

        $here = new Channel(1);
        $there = new Channel(1);

        /** @var array{string, string} $handles */
        $handles = CoroutinePool::fromCoroutines(
            static fn(): string => self::useEitherSideOfTheOther($client, $here, $there),
            static fn(): string => self::useEitherSideOfTheOther($client, $there, $here),
        )->run();
        [$first, $second] = $handles;

        $output->writeln(sprintf('Coroutine A handles: %s', $first));
        $output->writeln(sprintf('Coroutine B handles: %s', $second));

        return self::SUCCESS;
    }

    /**
     * Asked of an object rather than of the declared type: both classes are final, so statically neither
     * can be a proxy - z-engine is what removes `final`, at runtime, before a proxy extends them.
     *
     * @param class-string $type
     */
    private static function isA(object $object, string $type): bool
    {
        return $object instanceof $type;
    }

    private static function readonlyOrNot(object $object): string
    {
        return (new ReflectionClass($object))->isReadOnly() ? 'readonly' : 'not readonly';
    }

    private static function useEitherSideOfTheOther(ReadonlyCurlClient $client, Channel $here, Channel $there): string
    {
        $before = $client->handleId();

        $here->push(true);
        $there->pop();

        return sprintf('%d %d', $before, $client->handleId());
    }
}
