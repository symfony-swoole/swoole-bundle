<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use Override;
use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\Exception\WorkerMaxRequestUnsupported;
use SwooleBundle\SwooleBundle\Server\Configurator\Configurator;
use SwooleBundle\SwooleBundle\Server\HttpServerConfiguration;

/**
 * EXPERIMENTAL. Refuses to start a server whose blocking task worker commands worker_max_request would
 * stop for good.
 *
 * The extension refuses the same combination at compile time whenever it can see the number, which is
 * most of the time. This is for the rest: a worker_max_request taken from an env var is a placeholder
 * until the server runs, and only the resolved configuration says what it is. Configurators run in the
 * master before anything is forked, so the server exits with the reason instead of starting and stopping
 * its consumers at the first recycle.
 *
 * Registered only with coroutines off; with them on, the task workers raise the stop flag themselves and
 * worker_max_request is safe.
 *
 * @see Exception\WorkerMaxRequestUnsupported for why
 */
final readonly class RefuseMaxRequestWithBlockingCommands implements Configurator
{
    public function __construct(private HttpServerConfiguration $configuration) {}

    #[Override]
    public function configure(Server $server): void
    {
        $this->check();
    }

    /**
     * Apart from configure() so it can be asked without a server, which binding a port is the price of.
     *
     * @throws WorkerMaxRequestUnsupported
     */
    public function check(): void
    {
        $maxRequest = $this->configuration->getSwooleMaxRequest();

        if ($maxRequest <= 0) {
            return;
        }

        throw WorkerMaxRequestUnsupported::forBlockingCommands($maxRequest);
    }
}
