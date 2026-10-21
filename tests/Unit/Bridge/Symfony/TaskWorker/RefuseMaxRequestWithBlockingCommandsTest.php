<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\TaskWorker;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\Exception\WorkerMaxRequestUnsupported;
use SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker\RefuseMaxRequestWithBlockingCommands;
use SwooleBundle\SwooleBundle\Server\HttpServerConfiguration;

/**
 * The runtime half of refusing worker_max_request with blocking commands - the half that sees a value
 * the container only had as an env placeholder.
 */
final class RefuseMaxRequestWithBlockingCommandsTest extends TestCase
{
    public function testThatNoMaxRequestIsAccepted(): void
    {
        $this->expectNotToPerformAssertions();

        $this->guard(0)->check();
    }

    public function testThatAMaxRequestIsRefused(): void
    {
        $this->expectException(WorkerMaxRequestUnsupported::class);
        $this->expectExceptionMessage('worker_max_request is 500');

        $this->guard(500)->check();
    }

    private function guard(int $maxRequest): RefuseMaxRequestWithBlockingCommands
    {
        $configuration = $this->createStub(HttpServerConfiguration::class);
        $configuration->method('getSwooleMaxRequest')
            ->willReturn($maxRequest);

        return new RefuseMaxRequestWithBlockingCommands($configuration);
    }
}
