<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route as RouteAnnotation;
use Symfony\Component\Routing\Attribute\Route;

final class IndexController
{
    /**
     * @RouteAnnotation(
     *     methods={"GET"},
     *     path="/"
     * )
     */
    #[Route(path: '/', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return new JsonResponse(['hello' => 'world!'], 200);
    }

    /**
     * @RouteAnnotation(
     *     methods={"GET"},
     *     path="/dummy-sleep"
     * )
     */
    #[Route(path: '/dummy-sleep', methods: ['GET'])]
    public function sleep(): JsonResponse
    {
        \co::sleep(2);

        return new JsonResponse(['hello' => 'world!'], 200);
    }
}
