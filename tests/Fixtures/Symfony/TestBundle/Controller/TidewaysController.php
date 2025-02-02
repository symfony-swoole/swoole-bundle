<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tideways\Profiler;

final class TidewaysController
{
    /**
     * @throws Exception
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
