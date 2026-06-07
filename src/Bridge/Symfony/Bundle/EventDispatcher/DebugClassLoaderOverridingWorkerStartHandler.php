<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\EventDispatcher;

use ReflectionProperty;
use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\WorkerHandler\WorkerStartHandler;
use Symfony\Component\ErrorHandler\DebugClassLoader;

/**
 * Disables the Symfony DebugClassLoader's case-sensitivity checking on macOS (Darwin).
 *
 * On macOS with coroutines enabled, the DebugClassLoader's darwin realpath logic
 * (which uses chdir/getcwd/scandir to normalize case-insensitive filesystem paths)
 * causes race conditions when multiple coroutines try to autoload classes concurrently.
 * The chdir/getcwd operations are process-global and not coroutine-safe, and scandir
 * is a hookable IO operation that yields the coroutine, creating windows where other
 * coroutines can step on the process working directory.
 *
 * This handler runs at onWorkerStart (the earliest safe point in a worker process)
 * and disables the case check (caseCheck = 2 → 0) via reflection. This prevents
 * DebugClassLoader::checkCase() from calling the problematic darwinRealpath method.
 *
 * The patch must happen in onWorkerStart, not in the parent process boot phase,
 * because most class loading happens during initializeContainer() before SwooleBundle::boot(),
 * and those early loads would still use caseCheck=2. By patching in onWorkerStart,
 * we ensure the flag is correct for all coroutine-based class loading.
 */
final readonly class DebugClassLoaderOverridingWorkerStartHandler implements WorkerStartHandler
{
    public function __construct(
        private WorkerStartHandler $decorated,
    ) {}

    public function handle(Server $server, int $workerId): void
    {
        $caseCheck = new ReflectionProperty(DebugClassLoader::class, 'caseCheck');
        $caseCheckValue = $caseCheck->getValue();

        if ($caseCheckValue === 2) { // disable case check on OSX with coroutines
            $caseCheck->setValue(null, 0);
        }

        $this->decorated->handle($server, $workerId);
    }
}
