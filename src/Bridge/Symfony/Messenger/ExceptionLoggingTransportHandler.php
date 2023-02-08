<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Messenger;

use K911\Swoole\Server\TaskHandler\TaskHandlerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Server;

final class ExceptionLoggingTransportHandler implements TaskHandlerInterface
{
    public function __construct(
        private TaskHandlerInterface $decorated,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function handle(Server $server, Server\Task $task): void
    {
        try {
            $this->decorated->handle($server, $task);
        } catch (\Throwable $e) {
            $this->logger->critical(
                sprintf('Task worker exception: %s', $e->getMessage()),
                [
                    'exception' => $e,
                ]
            );
        }
    }
}
