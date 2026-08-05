<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command;

use Override;
use Psr\Cache\CacheItemPoolInterface;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use SwooleBundle\SwooleBundle\Coroutine\CoroutinePool;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reports whether the profiler's cache-recording decorator was made coroutine-safe.
 *
 * Two things have to hold at once, and they pull in opposite directions: the decorator records calls per
 * request, so each coroutine needs one of its own - while the pool it wraps is a cache, which is only
 * worth having if every coroutine sees the same one.
 */
#[AsCommand(
    name: 'test:cache-adapter:proxy-check',
    description: 'Tells whether the traced cache pool is per-coroutine while its cache stays shared.',
)]
final class CacheAdapterProxyCheckCommand extends Command
{
    private const string CACHE_KEY = 'swoole_bundle_proxy_check';

    /**
     * A Doctrine pool on purpose. FrameworkBundle's own pools are already pooled per coroutine for other
     * reasons, so they cannot tell whether the recording decorator is being handled - the pools
     * DoctrineBundle registers are the ones that were left shared.
     */
    public function __construct(
        #[Autowire(service: 'cache.doctrine.orm.default.result')]
        private readonly CacheItemPoolInterface $cachePool,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isProxified = $this->cachePool instanceof ContextualProxy ? 'IS' : 'IS NOT';
        $output->writeln(sprintf('traced cache pool %s proxified.', $isProxified));

        [$recorders, $pools] = $this->resolveFromTwoCoroutines();

        $output->writeln(sprintf(
            'coroutines %s the call log.',
            count(array_unique($recorders)) === 1 ? 'SHARE' : 'DO NOT SHARE',
        ));
        $output->writeln(sprintf(
            'coroutines %s the cache.',
            count(array_unique($pools)) === 1 ? 'SHARE' : 'DO NOT SHARE',
        ));

        $output->writeln(sprintf('cache %s.', $this->cacheStillWorks() ? 'WORKS' : 'FAILED'));

        return self::SUCCESS;
    }

    /**
     * @return array{list<int>, list<int>}
     */
    private function resolveFromTwoCoroutines(): array
    {
        $cachePool = $this->cachePool;

        $resolve = static function () use ($cachePool): array {
            $contextual = $cachePool instanceof ContextualProxy ? $cachePool->getContextualObject() : $cachePool;

            // the recorder holding the per-request call log, and the pool it wraps holding the cache
            $wrapped = $contextual instanceof TraceableAdapter ? $contextual->getPool() : $contextual;

            return [['recorder' => spl_object_id($contextual), 'pool' => spl_object_id($wrapped)]];
        };

        $resolved = array_merge(...CoroutinePool::fromCoroutines($resolve, $resolve)->run());

        return [array_column($resolved, 'recorder'), array_column($resolved, 'pool')];
    }

    /**
     * That the pool is still usable through the proxy, and that what one coroutine caches is what the
     * next one reads.
     *
     * Note this says nothing about resetting: pool resetters run when a request coroutine ends, which
     * never happens in a console command. Which resetter the recording decorator gets - and why it must
     * not be reset() - is pinned by CacheAdapterProcessorTest instead.
     */
    private function cacheStillWorks(): bool
    {
        $save = function (): array {
            $item = $this->cachePool->getItem(self::CACHE_KEY);
            $item->set('stored');
            $this->cachePool->save($item);

            return [];
        };

        $read = [];
        // phpcs:ignore SlevomatCodingStandard.PHP.DisallowReference
        $readBack = function () use (&$read): array {
            $read[] = $this->cachePool->getItem(self::CACHE_KEY)->get();

            return [];
        };

        CoroutinePool::fromCoroutines($save)->run();
        CoroutinePool::fromCoroutines($readBack)->run();

        return ($read[0] ?? null) === 'stored';
    }
}
