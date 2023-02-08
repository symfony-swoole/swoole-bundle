<?php

declare(strict_types=1);

namespace K911\Swoole\Server\LifecycleHandler;

use Swoole\Process;
use Swoole\Server;

final class SigIntHandler implements ServerStartHandlerInterface
{
    private $signalInterrupt;

    public function __construct(private ?ServerStartHandlerInterface $decorated = null)
    {
        $this->signalInterrupt = \defined('SIGINT') ? (int) \constant('SIGINT') : 2;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Server $server): void
    {
        // 2 => SIGINT
        Process::signal($this->signalInterrupt, function () use ($server) {
            $server->stop();
            $server->shutdown();
        });

        if ($this->decorated instanceof ServerStartHandlerInterface) {
            $this->decorated->handle($server);
        }
    }
}
