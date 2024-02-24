<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use BlackfireProbe;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class BlackfireController
{
    /**
     * @throws Exception
     */
    #[Route(path: '/blackfire/index', methods: ['GET'])]
    public function indexAction(): Response
    {
        return new JsonResponse([
            'started' => BlackfireProbe::wasStarted(), /* @phpstan-ignore-line */
            'stopped' => BlackfireProbe::wasStopped(), /* @phpstan-ignore-line */
        ]);
    }
}
