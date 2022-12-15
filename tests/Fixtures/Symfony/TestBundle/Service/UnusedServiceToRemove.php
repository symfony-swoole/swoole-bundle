<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service;

use Symfony\Contracts\Service\ResetInterface;

final class UnusedServiceToRemove implements ResetInterface
{
    public function reset(): void
    {
    }
}
