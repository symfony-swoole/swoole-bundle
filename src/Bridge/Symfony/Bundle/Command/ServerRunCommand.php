<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\Command;

final class ServerRunCommand extends ServerExecutionCommand
{
    protected function configure(): void
    {
        $this->setDescription('Run Swoole HTTP server.');

        parent::configure();
    }
}
