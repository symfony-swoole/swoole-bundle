<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\Command;

final class ServerRunCommand extends AbstractServerStartCommand
{
    protected function configure(): void
    {
        $this->setDescription('Run Swoole HTTP server.');

        parent::configure();
    }
}
