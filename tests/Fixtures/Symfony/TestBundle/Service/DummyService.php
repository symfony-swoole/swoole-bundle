<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service;

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Entity\Test;

interface DummyService
{
    /**
     * @return array<Test>
     */
    public function process(): array;

    public function reset(): void;
}
