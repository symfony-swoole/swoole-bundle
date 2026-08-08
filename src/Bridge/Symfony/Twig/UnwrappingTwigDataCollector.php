<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Twig;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use Symfony\Bridge\Twig\DataCollector\TwigDataCollector;
use Twig\Environment;
use Twig\Profiler\Profile;

/**
 * Stores the profiling tree under the class it was collected from, not the proxy it was reached through.
 *
 * The collector keeps the profile for the toolbar as a serialized string, written at the end of the
 * request it belongs to and read back by whichever later request renders the panel:
 *
 * ```php
 * public function lateCollect(): void
 * {
 *     $this->data['profile'] = serialize($this->profile);
 * ```
 * ```php
 * public function getProfile(): Profile
 * {
 *     return $this->profile ??= unserialize($this->data['profile'], ['allowed_classes' => [Profile::class]]);
 * ```
 *
 * Pooled, the profile it is handed is a proxy, and serialize() writes the class of the object it is given -
 * no magic method gets a say in that. The allow list names one class and one class only, so the payload
 * comes back as __PHP_Incomplete_Class and assigning it to the collector's typed property is a fatal.
 *
 * Widening the allow list would be answering it in the wrong place: the payload outlives the worker that
 * wrote it, and a proxy class is only loadable by a worker whose generated proxies still carry the same
 * name. Handing serialize() the instance the proxy stands for keeps the stored profile exactly what
 * Symfony expects to read - loadable by any process, whether the bundle is running or not.
 *
 * The collector is @final by docblock only, and this leans on the one thing that annotation asks not to
 * be leaned on. Writing a collector of its own instead would mean carrying a copy of the template path
 * walk lateCollect() does, which is the part most likely to drift; overriding one line and keeping the
 * rest is the smaller bet. SymfonyProfilerTest renders the toolbar end to end and is what catches it
 * going wrong.
 *
 * @phpstan-ignore class.extendsFinalByPhpDoc
 */
final class UnwrappingTwigDataCollector extends TwigDataCollector
{
    public function __construct(
        private readonly Profile $profile,
        ?Environment $twig = null,
    ) {
        parent::__construct($profile, $twig);
    }

    public function lateCollect(): void
    {
        parent::lateCollect();

        // Profile is declared final, so nothing can implement ContextualProxy as far as static analysis
        // is concerned - the proxy generator strips final off the class it extends. Left unpooled, the
        // profile is a plain one and the parent has already stored it correctly.
        // @phpstan-ignore instanceof.alwaysFalse
        if (!$this->profile instanceof ContextualProxy) {
            return;
        }

        // the parent has just serialized the proxy; the tree is walked either way, so all this costs
        // is writing the payload a second time, and it keeps the path collection with its owner
        // @phpstan-ignore-next-line
        $this->data['profile'] = serialize($this->profile->getContextualObject());
    }
}
