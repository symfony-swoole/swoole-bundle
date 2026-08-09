<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Twig;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\Proxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Gives every coroutine its own root of Twig's profiling tree.
 *
 * With the Twig profiler on, every render enters and leaves nodes of one shared Profile, and
 * TwigDataCollector empties it again at the end of the request:
 *
 * ```php
 * public function reset(): void
 * {
 *     $this->profile->reset();
 *     ...
 * }
 * ```
 *
 * Shared by a worker that is rendering for several requests at once, one request's template stack is
 * nested inside another's, and whichever finishes first throws away the tree the rest are still filling
 * in - which is where the reset lands in somebody else's coroutine.
 *
 * The profile is not resettable in Symfony's sense and carries no tag saying it holds state, so nothing
 * else picks it up: saying so here is what makes it poolable at all. No resetter is needed with it,
 * because the data collector holding it is pooled too and resets the profile it was given.
 *
 * Pooling it costs the data collector its ability to store the profile for the toolbar, which is why the
 * collector is replaced along with it - see {@see UnwrappingTwigDataCollector}.
 */
final class TwigProcessor implements CompileProcessor
{
    private const string PROFILE_ID = 'twig.profile';
    private const string DATA_COLLECTOR_ID = 'data_collector.twig';

    public function process(ContainerBuilder $container, Proxifier $proxifier): void
    {
        if (!$container->hasDefinition(self::PROFILE_ID)) {
            return;
        }

        $container->getDefinition(self::PROFILE_ID)
            ->addTag(ContainerConstants::TAG_STATEFUL_SERVICE);

        // the profile exists whenever the profiler extension does; the collector only with the profiler
        // itself, so an application collecting nothing keeps Symfony's own class
        if (!$container->hasDefinition(self::DATA_COLLECTOR_ID)) {
            return;
        }

        $container->getDefinition(self::DATA_COLLECTOR_ID)
            ->setClass(UnwrappingTwigDataCollector::class);
    }
}
