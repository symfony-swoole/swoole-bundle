<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Twig;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\ServiceProxifier;
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
 *
 * The extensions driving that tree hold per-request state of their own, and get an instance each along
 * with it - see {@see self::PROFILER_EXTENSION_IDS}.
 */
final class TwigProcessor implements CompileProcessor
{
    private const string PROFILE_ID = 'twig.profile';
    private const string DATA_COLLECTOR_ID = 'data_collector.twig';
    private const string ENVIRONMENT_ID = 'twig';

    /**
     * The two Twig extensions that count where a render is, rather than just answering questions about it.
     *
     * `twig.extension.profiler` keeps the stack of open profiles in `$actives` and a running stopwatch
     * event per profile in `$events`; `twig.extension.webprofiler` keeps `$stackLevel` plus the memory
     * stream `profiler_dump()` writes into. Both are written by every enter() and leave() of every
     * template, block and macro rendered.
     *
     * Twig is pooled - `twig` is tagged `kernel.reset` for resetGlobals(), so every coroutine renders
     * through an Environment of its own - but a shared extension puts one set of those counters behind
     * all of them. Two concurrent renders then interleave into the same stack, and fiber_viber reports
     * the second one writing what the first owns.
     *
     * Which is a 500 out of whichever request lost - in dev, typically the toolbar's own stylesheet
     * route, taking the page's layout with it.
     *
     * Not shared is the whole fix: the extension is reached only through the Environment it was added
     * to, so one per Environment is one per coroutine, released and reused on exactly the schedule the
     * Environment already is.
     *
     * Pooling them instead - the answer everywhere else in this pass - is the one thing that cannot
     * work here. ProfilerExtension::getNodeVisitors() names the extension to look up at render time by
     * `static::class`, and the compiled template reads `$this->extensions[<that name>]`, an array
     * ExtensionSet keys by `$extension::class`. A proxy forwards getNodeVisitors() to the instance
     * behind it, so the name baked into the template is the real class while the key in the array is
     * the generated proxy - and every template rendered dies on the missing key.
     */
    private const array PROFILER_EXTENSION_IDS = [
        'twig.extension.profiler',
        'twig.extension.webprofiler',
    ];

    public function process(ContainerBuilder $container, ServiceProxifier $proxifier): void
    {
        $this->unshareProfilerExtensions($container);

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

    private function unshareProfilerExtensions(ContainerBuilder $container): void
    {
        // WebProfilerBundle registers its extension whether or not Twig is installed, and an extension
        // no Environment ever receives is constructed by nobody - unsharing it would only make the
        // container harder to read.
        if (!$container->hasDefinition(self::ENVIRONMENT_ID)) {
            return;
        }

        foreach (self::PROFILER_EXTENSION_IDS as $extensionId) {
            if (!$container->hasDefinition($extensionId)) {
                continue;
            }

            $container->getDefinition($extensionId)->setShared(false);
        }
    }
}
