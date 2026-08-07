<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class BlockingController
{
    #[Route(path: '/test/blocking/{milliseconds}', methods: ['GET'], requirements: ['milliseconds' => '\d+'])]
    public function block(int $milliseconds): JsonResponse
    {
        usleep($milliseconds * 1000);

        return new JsonResponse(['blocked' => $milliseconds], 200);
    }
}
