<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;

final class CloningController
{
    #[Route(path: '/clone', methods: ['GET'])]
    public function index(RequestStack $requestStack): JsonResponse
    {
        $cloned = clone $requestStack;
        $cloned->pop();

        return new JsonResponse(
            [
                'original_has_request' => $requestStack->getCurrentRequest() instanceof Request,
                'cloned_does_not_have_request' => $cloned->getCurrentRequest() === null,
            ],
            200,
        );
    }
}
