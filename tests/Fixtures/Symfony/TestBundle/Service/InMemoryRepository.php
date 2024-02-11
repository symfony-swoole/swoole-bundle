<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service;

use RuntimeException;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Entity\Test;
use Symfony\Contracts\Service\ResetInterface;

final class InMemoryRepository implements ResetInterface
{
    private ?Test $storedValue = null;

    public function store(Test $test): void
    {
        if ($this->storedValue !== null) {
            throw new RuntimeException('Repository was not reset.');
        }

        $this->storedValue = $test;
    }

    public function reset(): void
    {
        $this->storedValue = null;
    }
}
