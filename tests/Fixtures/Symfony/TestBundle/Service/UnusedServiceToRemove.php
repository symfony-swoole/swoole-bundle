<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service;

use Symfony\Contracts\Service\ResetInterface;

final class UnusedServiceToRemove implements ResetInterface
{
    public function reset(): void {}
}
