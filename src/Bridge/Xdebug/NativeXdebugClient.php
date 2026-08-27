<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Xdebug;

use Override;

/**
 * Opens a step-debugging session through the xdebug extension.
 *
 * The only implementation that talks to xdebug. It is kept apart from the interface so the handlers
 * can be exercised without the extension - a test suite cannot assume a container that has it loaded,
 * and the interesting behaviour is in when the handlers attach rather than in what attaching does.
 *
 * Everything here is guarded at runtime rather than at compile time, deliberately. Whether the
 * extension is loaded is a property of the process, and a compiled container outlives it: a container
 * built while xdebug was off is reused unchanged by a process that has it on, which is exactly what
 * happens when a debugger is switched on by recreating a container. A compile-time check would decide
 * the wrong way round for the life of that cache.
 *
 * @see docs/swoole-xdebug.md
 */
final readonly class NativeXdebugClient implements XdebugClient
{
    /**
     * Both functions are checked, not just the one used: xdebug_connect_to_client() arrived in 3.2,
     * and being able to ask for a session without being able to ask whether one is already open would
     * mean reconnecting on every call.
     */
    #[Override]
    public function isAvailable(): bool
    {
        return function_exists('xdebug_connect_to_client') && function_exists('xdebug_is_debugger_active');
    }

    #[Override]
    public function isAttached(): bool
    {
        return $this->isAvailable() && xdebug_is_debugger_active();
    }

    /**
     * The return value of xdebug_connect_to_client() is deliberately ignored: it reports that the
     * attempt was made rather than that it succeeded, and there is nothing useful to do with a failure
     * here anyway. A request or a task must be served whether or not a client was listening, and
     * xdebug has already written the reason to its own log if xdebug.log is set.
     */
    #[Override]
    public function attach(): void
    {
        if (!$this->isAvailable() || $this->isAttached()) {
            return;
        }

        xdebug_connect_to_client();
    }
}
