<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\Command;

use Override;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\Exception\CouldNotCreatePidFileException;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\Exception\PidFileNotAccessibleException;
use SwooleBundle\SwooleBundle\Server\HttpServer;
use SwooleBundle\SwooleBundle\Server\HttpServerConfiguration;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ServerStartCommand extends ServerExecutionCommand
{
    #[Override]
    protected function configure(): void
    {
        $this->setDescription('Run Swoole HTTP server in the background.')
            ->addOption(
                'pid-file',
                null,
                InputOption::VALUE_REQUIRED,
                'Pid file',
                $this->getProjectDirectory() . '/var/swoole.pid'
            );

        parent::configure();
    }

    #[Override]
    protected function prepareServerConfiguration(
        HttpServerConfiguration $serverConfiguration,
        InputInterface $input,
    ): void {
        /** @var string|null $pidFile */
        $pidFile = $input->getOption('pid-file');
        $serverConfiguration->daemonize($pidFile);

        parent::prepareServerConfiguration($serverConfiguration, $input);
    }

    #[Override]
    protected function startServer(
        HttpServerConfiguration $serverConfiguration,
        HttpServer $server,
        SymfonyStyle $io,
    ): void {
        $pidFile = $serverConfiguration->getPidFile();

        if (!touch($pidFile)) {
            throw PidFileNotAccessibleException::forFile($pidFile);
        }

        if (!is_writable($pidFile)) {
            throw CouldNotCreatePidFileException::forPath($pidFile);
        }

        $server->start();
    }
}
