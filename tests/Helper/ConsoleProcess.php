<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Helper;

use Override;
use Symfony\Component\Process\Process;

/**
 * The console process a feature test drives, with its timeouts scaled to how busy the machine is.
 *
 * Feature tests bound their processes with timeouts as low as three seconds, which is generous for a
 * server booting on an otherwise idle machine and far too tight when a dozen of them boot at once. The
 * timeouts stay written as the single-run values they are - the scaling happens here, so no test has to
 * know whether it is one of many.
 */
final class ConsoleProcess extends Process
{
    #[Override]
    public function setTimeout(?float $timeout): static
    {
        return parent::setTimeout($this->scale($timeout));
    }

    #[Override]
    public function setIdleTimeout(?float $timeout): static
    {
        return parent::setIdleTimeout($this->scale($timeout));
    }

    private function scale(?float $timeout): ?float
    {
        return $timeout === null ? null : $timeout * TestToken::timeoutFactor();
    }
}
