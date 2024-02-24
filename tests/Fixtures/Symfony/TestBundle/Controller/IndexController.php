<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use co;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final class IndexController
{
    #[Route(path: '/', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return new JsonResponse(['hello' => 'world!'], 200);
    }

    #[Route(path: '/dummy-sleep', methods: ['GET'])]
    public function sleep(): JsonResponse
    {
        co::sleep(2);

        return new JsonResponse(['hello' => 'world!'], 200);
    }
}
