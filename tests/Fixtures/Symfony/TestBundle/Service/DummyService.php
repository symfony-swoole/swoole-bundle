<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service;

use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Entity\Test;

interface DummyService
{
    /**
     * @return Test[]
     */
    public function process(): array;

    public function reset(): void;
}
