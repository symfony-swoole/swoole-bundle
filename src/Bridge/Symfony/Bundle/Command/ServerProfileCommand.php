<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\Command;

use Assert\Assertion;
use Assert\AssertionFailedException;
use SwooleBundle\SwooleBundle\Server\HttpServerConfiguration;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @phpstan-import-type RuntimeConfiguration from ServerExecutionCommand
 */
final class ServerProfileCommand extends ServerExecutionCommand
{
    protected function configure(): void
    {
        $this->setDescription('Handle specified amount of requests to Swoole HTTP server. Useful for profiling.')
            ->addArgument('requests', InputArgument::REQUIRED, 'Number of requests to handle by the server');

        parent::configure();
    }

    /**
     * @return RuntimeConfiguration
     * @throws AssertionFailedException
     */
    protected function prepareRuntimeConfiguration(
        HttpServerConfiguration $serverConfiguration,
        InputInterface $input,
    ): array {
        $requestLimit = $input->getArgument('requests');
        Assertion::numeric($requestLimit);
        Assertion::greaterOrEqualThan($requestLimit, 0, 'Request limit must be greater than 0');
        $parentConfiguration = parent::prepareRuntimeConfiguration($serverConfiguration, $input);

        return ['requestLimit' => (int) $requestLimit] + $parentConfiguration;
    }

    /**
     * @param RuntimeConfiguration $runtimeConfiguration
     * @return array<array<string>>
     * @throws AssertionFailedException
     */
    protected function prepareConfigurationRowsToPrint(
        HttpServerConfiguration $serverConfiguration,
        array $runtimeConfiguration,
    ): array {
        $requestLimit = -1;

        if (isset($runtimeConfiguration['requestLimit']) && $runtimeConfiguration['requestLimit'] > 0) {
            $requestLimit = $runtimeConfiguration['requestLimit'];
        }

        $rows = parent::prepareConfigurationRowsToPrint($serverConfiguration, $runtimeConfiguration);
        $rows[] = ['request_limit', (string) $requestLimit];

        return $rows;
    }
}
