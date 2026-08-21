<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ContainerBuilderDebugDumpPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Rewrites the debug container dump after {@see StatefulServicesPass} has proxified everything.
 *
 * `debug:container` does not read the container the application runs; in a debug kernel it reads the
 * XML (and the `.ser` twin beside it) that FrameworkBundle dumps to `%debug.container.dump%`. That dump
 * is written by {@see ContainerBuilderDebugDumpPass}, registered `TYPE_BEFORE_REMOVING` at priority
 * -255, while StatefulServicesPass runs in the same stage at -10000 - later, because it has to see the
 * definitions every other pass has finished with. The dump is therefore a snapshot of the container as
 * it was before a single service was pooled, and `debug:container` shows a `twig` that is still plain
 * Twig, with no sign of the pool, the proxy or the wrapped original beside it.
 *
 * Nothing is wrong with the container itself - only with the copy the debugging tools read. A kernel
 * without debug has no such copy: `BuildDebugContainerTrait` then rebuilds and recompiles the container,
 * which runs StatefulServicesPass along with everything else, and the pooled services have always shown
 * up there. This pass gives the debug kernel the same answer.
 *
 * Registered below StatefulServicesPass rather than moving that one above the dumper: its -10000 is
 * what puts it after every other bundle's before-removing passes and after the compile processors, so
 * raising it would reorder it against all of them and not just against the dumper.
 *
 * The file is dropped before the dumper is handed the container again, because
 * ContainerBuilderDebugDumpPass returns early on a fresh cache and the copy written at -255 is fresh by
 * definition. Deleting it is also what makes the `.ser` twin be rewritten, which matters because
 * BuildDebugContainerTrait prefers that one over the XML whenever it exists - a rewritten XML beside a
 * stale `.ser` would change nothing.
 */
final class DebugContainerRedumpPass implements CompilerPassInterface
{
    /**
     * Set by FrameworkExtension in a debug kernel only, which is exactly when there is a dump to fix.
     */
    private const string DUMP_PARAMETER = 'debug.container.dump';

    public function process(ContainerBuilder $container): void
    {
        // Without coroutines StatefulServicesPass returns without touching a definition, so the dump
        // taken at -255 already describes the container the application runs and rewriting it would be
        // a second XmlDumper run over the whole container for no change at all.
        if (!$this->areCoroutinesEnabled($container)) {
            return;
        }

        $file = $this->dumpFile($container);

        if ($file === null) {
            return;
        }

        @unlink($file);

        (new ContainerBuilderDebugDumpPass())->process($container);

        $container->log($this, sprintf(
            'Rewrote "%s" after the stateful services were proxified, so that debug:container reports '
            . 'the service pools instead of the container as it was before they were built.',
            self::DUMP_PARAMETER,
        ));
    }

    private function areCoroutinesEnabled(ContainerBuilder $container): bool
    {
        return $container->hasParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)
            && $container->getParameter(ContainerConstants::PARAM_COROUTINES_ENABLED) === true;
    }

    /**
     * The dump to rewrite, or null when this kernel keeps none.
     */
    private function dumpFile(ContainerBuilder $container): ?string
    {
        if (!$container->hasParameter(self::DUMP_PARAMETER)) {
            return null;
        }

        $file = $container->getParameter(self::DUMP_PARAMETER);

        return is_string($file) && $file !== '' ? $file : null;
    }
}
