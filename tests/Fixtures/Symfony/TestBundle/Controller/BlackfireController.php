<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as RouteAnnotation;
use Symfony\Component\Routing\Attribute\Route;

final class BlackfireController
{
    /**
     * @RouteAnnotation(
     *     methods={"GET"},
     *     path="/blackfire/index"
     * )
     *
     * @throws \Exception
     */
    #[Route(path: '/blackfire/index', methods: ['GET'])]
    public function indexAction(): Response
    {
        return new JsonResponse([
            'started' => \BlackfireProbe::wasStarted(), /* @phpstan-ignore-line */
            'stopped' => \BlackfireProbe::wasStopped(), /* @phpstan-ignore-line */
        ]);
    }
}
