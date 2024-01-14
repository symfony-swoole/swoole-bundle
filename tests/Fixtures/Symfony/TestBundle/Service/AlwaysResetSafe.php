<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service;

use Symfony\Contracts\Service\ResetInterface;

final class AlwaysResetSafe implements ResetInterface
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
