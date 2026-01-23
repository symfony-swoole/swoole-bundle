<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\Stub;

use Symfony\Component\Routing\Attribute\Route;

final class StubGrpcController
{
    #[Route('/test-grpc-route')]
    public function testMethod(): void {}

    public function otherMethod(): void {}
}
