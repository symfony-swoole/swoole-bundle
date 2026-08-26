<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger;

use Exception;
use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandler;
use Symfony\Contracts\Service\ResetInterface;

final readonly class ServiceResettingTransportHandler implements TaskHandler
{
    public function __construct(
        private TaskHandler $decorated,
        // the "services_resetter" service, hinted through the contract rather than through a concrete
        // ServicesResetter: Symfony 8.1 moved that class from HttpKernel to DependencyInjection and left
        // the old name behind as a deprecated subclass, so neither concrete name matches every version.
        private ResetInterface $resetter,
    ) {}

    /**
     * @throws Exception
     */
    public function handle(Server $server, Server\Task $task): void
    {
        $this->resetter->reset();
        $this->decorated->handle($server, $task);
    }
}
