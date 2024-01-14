<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service;

final class DecorationTestDummyService implements DummyService
{
    public function __construct(private readonly DummyService $decorated)
    {
    }

    public function process(): array
    {
        return $this->decorated->process();
    }

    public function getDecorated(): DummyService
    {
        return $this->decorated;
    }

    /**
     * this method has to be here because SF container decorating logic leaves removes the kernel.reset tag
     * from the decorated service and adds it to the decorating service.
     */
    public function reset(): void
    {
        $this->decorated->reset();
    }
}
