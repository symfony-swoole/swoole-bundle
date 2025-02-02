<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger;

use Psr\Log\LoggerInterface;
use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandler;
use Throwable;

final readonly class ExceptionLoggingTransportHandler implements TaskHandler
{
    public function __construct(
        private TaskHandler $decorated,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(Server $server, Server\Task $task): void
    {
        try {
            $this->decorated->handle($server, $task);
        } catch (Throwable $e) {
            $this->logger->critical(
                sprintf('Task worker exception: %s', $e->getMessage()),
                [
                    'exception' => $e,
                ]
            );
        }
    }
}
