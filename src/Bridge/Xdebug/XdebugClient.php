<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Xdebug;

/**
 * Opens step-debugging sessions on demand, for a server where xdebug cannot open them by itself.
 *
 * Xdebug decides whether to start a session when the PHP script starts, and under swoole the script is
 * the worker process rather than the request: a worker forks, boots, and then serves for hours without
 * PHP starting again. Everything xdebug can decide for itself is therefore decided once per worker, at
 * fork time - which is both too early and, for the master, in the wrong process entirely.
 *
 * The handlers around this interface pick the moment instead. What they need from it is small enough
 * to state in three questions, and small enough that a test can answer them without the extension.
 *
 * @see NativeXdebugClient for the implementation that talks to xdebug
 * @see docs/swoole-xdebug.md
 */
interface XdebugClient
{
    /**
     * Whether this process could be attached at all - false whenever the extension is not loaded,
     * which is the normal case.
     */
    public function isAvailable(): bool;

    /**
     * Whether this process is already in a session. True from the second attach onwards, since a
     * worker that has connected stays connected until the client lets go.
     */
    public function isAttached(): bool;

    /**
     * Attaches this process to the debugging client, unless it is unavailable or attached already.
     *
     * Returns nothing on purpose. Whether a client was listening is not something a caller can act on:
     * a request or a task has to be served either way.
     */
    public function attach(): void;
}
