<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpClient;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\ServiceProxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\TraceableHttpClient;

/**
 * Gives every coroutine its own traced http client, so the profiler's http panel belongs to the
 * request it is shown for.
 *
 * Every call made through a traced client is appended to one ArrayObject on the client itself, and
 * the collector empties it again once it has read it:
 *
 * ```php
 * // request()
 * $this->tracedRequests[] = $tracedRequest;
 * ...
 * // reset()
 * $this->tracedRequests->exchangeArray([]);
 * ```
 *
 * A trace holds the response it produced, and a buffered response holds the `php://temp` its body was
 * read into. So the coroutine whose `kernel.response` gets to HttpClientDataCollector::lateCollect()
 * first drops every other in-flight request's traces - closing buffers those requests are still
 * filling, from a coroutine that does not own them:
 *
 *   FiberViber\ConcurrencyException: Cross-coroutine access detected: [stream_close] php://temp is
 *   owned by coroutine #88 but accessed by coroutine #...
 *
 * with the same conflict reported a moment earlier on the [stream_write] and [stream_flush] that
 * closing the stream flushes. Without fiber_viber watching, the same sharing is silent and shows up
 * instead as a profiler panel listing another request's http calls, or listing none because somebody
 * else's collect ran first.
 *
 * Symfony marks the traced client resettable itself - HttpClientPass registers `.debug.<id>` with a
 * `kernel.reset` tag - and nothing here would be needed if that tag survived. It does not:
 * DecoratorServicePass migrates the tags of every decorator in a chain onto whichever one ends up
 * outermost, so with `http_client` decorated more than once the traced client is left with no tags at
 * all. In a stock Symfony 7 application the tag ends on `http_client.uri_template`, and this bundle
 * pools that and the transport at the bottom of the chain while the traced client in between - the one
 * link that actually accumulates per-request state - stays shared.
 *
 * Which is why this matches on the class rather than on the tag: what a definition is called and what
 * tags it still carries after the decoration chain is resolved are both incidental, but a
 * TraceableHttpClient is a TraceableHttpClient. See the loop for why the class is compared rather
 * than asked about.
 *
 * No resetter has to be named. The class implements ResetInterface, which is StatefulServicesPass's
 * documented fallback for a pooled service that arrives without one, and reset() is exactly the right
 * way to hand the client back: it empties the traces and passes the reset on to the client it wraps.
 */
final class HttpClientProcessor implements CompileProcessor
{
    public function process(ContainerBuilder $container, ServiceProxifier $proxifier): void
    {
        foreach ($container->getDefinitions() as $definition) {
            // Compared as a string, and never with is_a()'s autoloading form: this runs over every
            // definition in the container, and plenty of them name a class that cannot be loaded in
            // the application being compiled - an optional integration whose package is absent, a
            // validator whose parent lives behind a suggest. Autoloading each one to ask what it is
            // takes the whole compilation down with the first that will not load. Exact comparison
            // loses nothing here, because TraceableHttpClient is final and has no subclass to miss.
            if ($definition->getClass() !== TraceableHttpClient::class) {
                continue;
            }

            if ($definition->hasTag(ContainerConstants::TAG_STATEFUL_SERVICE)) {
                continue;
            }

            $definition->addTag(ContainerConstants::TAG_STATEFUL_SERVICE);
        }
    }
}
