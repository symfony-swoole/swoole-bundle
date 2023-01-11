<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service;

use Symfony\Contracts\Service\ResetInterface;

final class AlwaysReset implements ResetInterface
{
    private bool $wasReset = false;

    public function reset(): void
    {
        $this->wasReset = true;
    }

    public function getWasReset(): bool
    {
        return $this->wasReset;
    }
}
