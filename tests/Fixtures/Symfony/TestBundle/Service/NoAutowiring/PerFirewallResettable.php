<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\NoAutowiring;

use Symfony\Contracts\Service\ResetInterface;

/**
 * A service a bundle registers once as a template and then once per firewall, with the firewall's name
 * filled in - the shape scheb/2fa-bundle gives its provider preparation listener.
 */
final class PerFirewallResettable implements ResetInterface
{
    private bool $wasReset = false;

    public function __construct(private readonly string $firewallName) {}

    public function reset(): void
    {
        $this->wasReset = true;
    }

    public function getFirewallName(): string
    {
        return $this->firewallName;
    }

    public function getWasReset(): bool
    {
        return $this->wasReset;
    }
}
