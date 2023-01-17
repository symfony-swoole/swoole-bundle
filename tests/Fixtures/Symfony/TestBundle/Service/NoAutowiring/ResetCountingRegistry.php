<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service\NoAutowiring;

use Doctrine\Bundle\DoctrineBundle\Registry;

class ResetCountingRegistry extends Registry
{
    private int $resetCount = 0;

    public function reset(): void
    {
        ++$this->resetCount;
        parent::reset();
    }

    public function getResetCount(): int
    {
        return $this->resetCount;
    }
}
