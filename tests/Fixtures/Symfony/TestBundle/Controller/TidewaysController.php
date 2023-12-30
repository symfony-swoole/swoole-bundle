<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as RouteAnnotation;
use Symfony\Component\Routing\Attribute\Route;
use Tideways\Profiler;

final class TidewaysController
{
    /**
     * @RouteAnnotation(
     *     methods={"GET"},
     *     path="/tideways/index"
     * )
     *
     * @throws \Exception
     */
    #[Route(path: '/tideways/index', methods: ['GET'])]
    public function indexAction(): Response
    {
        return new JsonResponse([
            'started' => Profiler::$wasStarted,
            'stopped' => Profiler::$wasStopped,
        ]);
    }
}
