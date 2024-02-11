<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation;

use SwooleBundle\SwooleBundle\Server\Runtime\Bootable;
use Symfony\Component\HttpFoundation\Request;

/**
 * Sets symfony's request runtime configuration.
 *
 * @phpstan-import-type RuntimeConfiguration from Bootable
 */
final class SetRequestRuntimeConfiguration implements Bootable
{
    /**
     * @param RuntimeConfiguration $runtimeConfiguration
     */
    public function boot(array $runtimeConfiguration = []): void
    {
        if (array_key_exists('trustedHosts', $runtimeConfiguration)) {
            Request::setTrustedHosts($runtimeConfiguration['trustedHosts']);
        }
        if (!array_key_exists('trustedProxies', $runtimeConfiguration)) {
            return;
        }

        Request::setTrustedProxies(
            $runtimeConfiguration['trustedProxies'],
            $runtimeConfiguration['trustedHeaderSet'] ?? TrustAllProxiesRequestHandler::HEADER_X_FORWARDED_ALL
        );
    }
}
