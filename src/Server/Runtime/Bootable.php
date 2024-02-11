<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Runtime;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @phpstan-type RuntimeConfiguration = array{
 *   trustedHosts?: array<string>,
 *   trustedProxies?: array<string>,
 *   trustAllProxies?: bool,
 *   requestLimit?: int,
 *   trustedHeaderSet?: int,
 *   symfonyStyle?: SymfonyStyle,
 *   nonReloadableFiles?: array<string>,
 * }
 */
interface Bootable
{
    /**
     * Used to provide or override configuration at runtime.
     *
     * This method will be called directly before starting Swoole server.
     *
     * @param RuntimeConfiguration $runtimeConfiguration
     */
    public function boot(array $runtimeConfiguration = []): void;
}
