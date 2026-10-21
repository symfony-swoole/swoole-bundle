<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\Exception;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * worker_max_request together with task worker commands and coroutines off.
 *
 * A configuration error rather than a runtime one, so a container that would compile it fails to.
 * Thrown again before the server starts, for a value only known then - an env placeholder.
 */
final class WorkerMaxRequestUnsupported extends InvalidConfigurationException
{
    public static function forBlockingCommands(int $maxRequest): self
    {
        return new self(sprintf(
            'swoole.http_server.settings.worker_max_request is %d, which cannot be combined with '
            . 'swoole.task_worker.commands while platform.coroutines.enabled is false. With coroutines off a '
            . 'command blocks its task worker, which then hears nothing of the server stopping; the only notice '
            . 'it gets is an http worker raising the stop flag on its way out - and an http worker recycled by '
            . 'worker_max_request leaves the same way, so every recycle would stop the commands for good. '
            . 'Either enable coroutines, where task workers raise the flag for themselves, or set '
            . 'worker_max_request to 0.',
            $maxRequest,
        ));
    }
}
