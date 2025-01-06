<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\Command;

use Assert\Assertion;
use Assert\AssertionFailedException;
use Exception;
use InvalidArgumentException;
use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Common\System\System;
use SwooleBundle\SwooleBundle\Common\XdebugHandler\XdebugHandler;
use SwooleBundle\SwooleBundle\Server\Config\Socket;
use SwooleBundle\SwooleBundle\Server\Configurator\Configurator;
use SwooleBundle\SwooleBundle\Server\HttpServer;
use SwooleBundle\SwooleBundle\Server\HttpServerConfiguration;
use SwooleBundle\SwooleBundle\Server\HttpServerFactory;
use SwooleBundle\SwooleBundle\Server\Runtime\Bootable;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException as SfInvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use function SwooleBundle\SwooleBundle\decode_string_as_set;
use function SwooleBundle\SwooleBundle\format_bytes;
use function SwooleBundle\SwooleBundle\get_max_memory;

/**
 * @phpstan-import-type RuntimeConfiguration from Bootable
 */
abstract class ServerExecutionCommand extends Command
{
    use ParametersHelper;

    private bool $testing = false;

    public function __construct(
        private readonly HttpServer $server,
        private readonly HttpServerConfiguration $serverConfiguration,
        private readonly Configurator $serverConfigurator,
        protected ParameterBagInterface $parameterBag,
        private readonly Bootable $bootManager,
    ) {
        parent::__construct();
    }

    public function enableTestMode(): void
    {
        $this->testing = true;
    }

    /**
     * Disables default POSIX signal handling on application assigning. Swoole doesn't support it.
     */
    public function setApplication(?Application $application = null): void
    {
        if ($application === null) {
            throw new InvalidArgumentException('Application cannot be null');
        }

        $application->setSignalsToDispatchEvent();

        parent::setApplication($application);
    }

    /**
     * @throws SfInvalidArgumentException
     * @throws AssertionFailedException
     */
    protected function configure(): void
    {
        $sockets = $this->serverConfiguration->getSockets();
        $serverSocket = $sockets->getServerSocket();
        $this->addOption(
            'host',
            null,
            InputOption::VALUE_REQUIRED,
            'Host name to bind to. To bind to any host, use: 0.0.0.0',
            $serverSocket->host()
        )
            ->addOption(
                'port',
                null,
                InputOption::VALUE_REQUIRED,
                'Listen for Swoole HTTP Server on this port, when 0 random available port is chosen',
                (string) $serverSocket->port()
            )
            ->addOption(
                'serve-static',
                's',
                InputOption::VALUE_NONE,
                'Enables serving static content from public directory'
            )
            ->addOption(
                'public-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Public directory',
                $this->getDefaultPublicDir()
            )
            ->addOption(
                'trusted-hosts',
                null,
                InputOption::VALUE_REQUIRED,
                'Trusted hosts',
                $this->parameterBag->get('swoole.http_server.trusted_hosts')
            )
            ->addOption(
                'trusted-proxies',
                null,
                InputOption::VALUE_REQUIRED,
                'Trusted proxies',
                $this->parameterBag->get('swoole.http_server.trusted_proxies')
            )
            ->addOption('api', null, InputOption::VALUE_NONE, 'Enable API Server')
            ->addOption(
                'api-port',
                null,
                InputOption::VALUE_REQUIRED,
                'Listen for API Server on this port',
                $this->parameterBag->get('swoole.http_server.api.port')
            );
    }

    /**
     * @throws SfInvalidArgumentException
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws AssertionFailedException
     */
    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->ensureXdebugDisabled($io);
        $this->prepareServerConfiguration($this->serverConfiguration, $input);

        if ($this->server->isRunning()) {
            $io->error('Swoole HTTP Server is already running');
            exit(1);
        }

        $swooleServer = $this->makeSwooleHttpServer();
        $this->serverConfigurator->configure($swooleServer);
        $this->server->attach($swooleServer);

        // TODO: Lock server configuration here
        //        $this->serverConfiguration->lock();

        $runtimeConfiguration = ['symfonyStyle' => $io] + $this->prepareRuntimeConfiguration(
            $this->serverConfiguration,
            $input
        );
        $this->bootManager->boot($runtimeConfiguration);

        $sockets = $this->serverConfiguration->getSockets();
        $serverSocket = $sockets->getServerSocket();
        $io->success(sprintf('Swoole HTTP Server started on http://%s', $serverSocket->addressPort()));
        if ($sockets->hasApiSocket()) {
            $io->success(sprintf('API Server started on http://%s', $sockets->getApiSocket()->addressPort()));
        }
        $io->table(
            ['Configuration', 'Values'],
            $this->prepareConfigurationRowsToPrint($this->serverConfiguration, $runtimeConfiguration)
        );

        if ($this->testing) {
            return 0;
        }

        $this->startServer($this->serverConfiguration, $this->server, $io);

        return 0;
    }

    /**
     * @throws AssertionFailedException
     */
    protected function prepareServerConfiguration(
        HttpServerConfiguration $serverConfiguration,
        InputInterface $input,
    ): void {
        $sockets = $serverConfiguration->getSockets();

        /** @var string $port */
        $port = $input->getOption('port');

        /** @var string|null $host */
        $host = $input->getOption('host');

        Assertion::numeric($port, 'Port must be a number.');
        Assertion::string($host, 'Host must be a string.');

        $newServerSocket = $sockets->getServerSocket()
            ->withPort((int) $port)
            ->withHost($host);

        $sockets->changeServerSocket($newServerSocket);

        if ((bool) $input->getOption('api') || $sockets->hasApiSocket()) {
            /** @var string $apiPort */
            $apiPort = $input->getOption('api-port');
            Assertion::numeric($apiPort, 'Port must be a number.');

            $sockets->changeApiSocket(new Socket('0.0.0.0', (int) $apiPort));
        }

        if (!filter_var($input->getOption('serve-static'), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $publicDir = $input->getOption('public-dir');
        Assertion::string($publicDir, 'Public dir must be a valid path');
        $serverConfiguration->enableServingStaticFiles($publicDir);
    }

    /**
     * @return RuntimeConfiguration
     * @throws AssertionFailedException
     */
    protected function prepareRuntimeConfiguration(
        HttpServerConfiguration $serverConfiguration,
        InputInterface $input,
    ): array {
        $trustedHosts = $input->getOption('trusted-hosts');
        Assertion::isArray($trustedHosts);
        Assertion::allString($trustedHosts);
        $trustedProxies = $input->getOption('trusted-proxies');
        Assertion::isArray($trustedProxies);
        Assertion::allString($trustedProxies);
        $runtimeConfiguration = [];
        $runtimeConfiguration['trustedHosts'] = $this->decodeSet($trustedHosts);
        $runtimeConfiguration['trustedProxies'] = $this->decodeSet($trustedProxies);

        if (in_array('*', $runtimeConfiguration['trustedProxies'], true)) {
            $runtimeConfiguration['trustAllProxies'] = true;
            $runtimeConfiguration['trustedProxies'] = array_filter(
                $runtimeConfiguration['trustedProxies'],
                static fn(string $trustedProxy): bool => $trustedProxy !== '*'
            );
        }

        return $runtimeConfiguration;
    }

    /**
     * Rows produced by this function will be printed on server startup in table with following form:
     * | Configuration | Value |.
     *
     * @param RuntimeConfiguration $runtimeConfiguration
     * @return array<array<string>>
     * @throws AssertionFailedException
     */
    protected function prepareConfigurationRowsToPrint(
        HttpServerConfiguration $serverConfiguration,
        array $runtimeConfiguration,
    ): array {
        $kernelEnv = $this->parameterBag->get('kernel.environment');
        Assertion::string($kernelEnv, 'Kernel environment must be a string.');

        $rows = [
            ['extension', System::create()->extension()->toString()],
            ['env', $kernelEnv],
            ['debug', (string) var_export($this->parameterBag->get('kernel.debug'), true)],
            ['user:group', $serverConfiguration->getUser() . ':' . $serverConfiguration->getGroup()],
            ['running_mode', $serverConfiguration->getRunningMode()],
            ['coroutines', $serverConfiguration->getCoroutinesEnabled() ? 'enabled' : 'disabled'],
            ['worker_count', (string) $serverConfiguration->getWorkerCount()],
            ['task_worker_count', (string) $serverConfiguration->getTaskWorkerCount()],
            ['reactor_count', (string) $serverConfiguration->getReactorCount()],
            ['worker_max_request', (string) $serverConfiguration->getMaxRequest()],
            ['worker_max_request_grace', (string) $serverConfiguration->getMaxRequestGrace()],
            ['memory_limit', format_bytes(get_max_memory())],
            ['trusted_hosts', implode(', ', $runtimeConfiguration['trustedHosts'] ?? [])],
        ];

        if (isset($runtimeConfiguration['trustAllProxies'])) {
            $rows[] = ['trusted_proxies', '*'];
        } else {
            $rows[] = ['trusted_proxies', implode(', ', $runtimeConfiguration['trustedProxies'] ?? [])];
        }

        if ($this->serverConfiguration->hasPublicDir()) {
            $rows[] = ['public_dir', $serverConfiguration->getPublicDir()];
        }

        if ($this->serverConfiguration->hasPublicDir()) {
            $rows[] = ['upload_tmp_dir', $serverConfiguration->getUploadTmpDir()];
        }

        return $rows;
    }

    protected function startServer(
        HttpServerConfiguration $serverConfiguration,
        HttpServer $server,
        SymfonyStyle $io,
    ): void {
        $io->comment('Quit the server with CONTROL-C.');

        if ($server->start()) {
            $io->newLine();
            $io->success('Swoole HTTP Server has been successfully shutdown.');
        } else {
            $io->error('Failure during starting Swoole HTTP Server.');
        }
    }

    /**
     * @throws AssertionFailedException
     */
    private function getDefaultPublicDir(): string
    {
        return $this->serverConfiguration->hasPublicDir() ? $this->serverConfiguration->getPublicDir() :
            $this->getProjectDirectory() . '/public';
    }

    private function ensureXdebugDisabled(SymfonyStyle $io): void
    {
        $xdebugHandler = new XdebugHandler();
        if (!$xdebugHandler->shouldRestart()) {
            return;
        }

        if ($xdebugHandler->canBeRestarted()) {
            $restartedProcess = $xdebugHandler->prepareRestartedProcess();
            $xdebugHandler->forwardSignals($restartedProcess);

            $io->note('Restarting command without Xdebug..');
            $io->comment(sprintf(
                "%s\n%s",
                'Swoole is incompatible with Xdebug. '
                . ' Check https://github.com/swoole/swoole-src/issues/1681 for more information.',
                sprintf('Set environment variable "%s=1" to use it anyway.', $xdebugHandler->allowXdebugEnvName())
            ));

            if ($this->testing) {
                return;
            }

            $restartedProcess->start();

            foreach ($restartedProcess as $processOutput) {
                echo $processOutput;
            }

            exit($restartedProcess->getExitCode());
        }

        $io->warning(
            sprintf(
                "Xdebug is enabled! Command could not be restarted automatically due to lack of \"pcntl\" "
                . "extension.\nPlease either disable Xdebug or set environment variable \"%s=1\" "
                . "to disable this message.",
                $xdebugHandler->allowXdebugEnvName()
            )
        );
    }

    private function makeSwooleHttpServer(): Server
    {
        $sockets = $this->serverConfiguration->getSockets();
        $serverSocket = $sockets->getServerSocket();

        return HttpServerFactory::make(
            $serverSocket,
            $this->serverConfiguration->getRunningMode(),
            ...($sockets->hasApiSocket() ? [$sockets->getApiSocket()] : [])
        );
    }

    /**
     * @param array<string>|string $set
     * @return array<string>
     * @throws AssertionFailedException
     */
    private function decodeSet(mixed $set): array
    {
        if (is_string($set)) {
            return decode_string_as_set($set);
        }

        if (count($set) === 1) {
            return decode_string_as_set($set[0]);
        }

        return $set;
    }
}
