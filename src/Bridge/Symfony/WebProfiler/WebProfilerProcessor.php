<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\WebProfiler;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\ServiceProxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Pools the profiler's Content-Security-Policy handler, and gives it a way back.
 *
 * `$cspDisabled` is written by every profiler controller action and by WebDebugToolbarListener, and
 * concurrent requests write it at the same time on the one shared instance:
 *
 *   FiberViber\ConcurrencyException: Cross-coroutine access detected: [property_write]
 *   Symfony\Bundle\WebProfilerBundle\Csp\ContentSecurityPolicyHandler::$cspDisabled is owned by
 *   coroutine #X but accessed by coroutine #Y
 *
 * Nothing marks the handler as stateful - it is not resettable in Symfony's sense and carries no tag -
 * so saying so here is what makes it poolable at all, the same way {@see TwigProcessor} does for the
 * profile.
 *
 * The resetter is the part that cannot be left out, and is why this is a processor rather than another
 * id in StatefulServicesPass's list: without one the pooled handler keeps whatever the last request
 * left on it, and since the only transition the class has is on, a handler that has served one toolbar
 * request stays disabled for every request it serves afterwards. See
 * {@see ContentSecurityPolicyHandlerResetter}.
 *
 * Debug-only by construction: WebProfilerBundle registers the handler and nothing else does.
 */
final class WebProfilerProcessor implements CompileProcessor
{
    private const string CSP_HANDLER_ID = 'web_profiler.csp.handler';
    private const string CSP_HANDLER_RESETTER_ID = 'swoole_bundle.web_profiler.csp_handler_resetter';

    public function process(ContainerBuilder $container, ServiceProxifier $proxifier): void
    {
        if (!$container->hasDefinition(self::CSP_HANDLER_ID)) {
            return;
        }

        if (!$container->hasDefinition(self::CSP_HANDLER_RESETTER_ID)) {
            $resetterDef = new Definition(ContentSecurityPolicyHandlerResetter::class);
            $resetterDef->setPublic(false);
            $container->setDefinition(self::CSP_HANDLER_RESETTER_ID, $resetterDef);
        }

        // Tagging rather than calling proxifyService(): the tag is what StatefulServicesPass acts on
        // once every compile processor has run, and doing both is refused outright by the Proxifier.
        $container->findDefinition(self::CSP_HANDLER_ID)
            ->addTag(ContainerConstants::TAG_STATEFUL_SERVICE, [
                'resetter' => self::CSP_HANDLER_RESETTER_ID,
            ]);
    }
}
