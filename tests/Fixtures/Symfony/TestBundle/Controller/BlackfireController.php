<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class BlackfireController
{
    /**
     * @Route(
     *     methods={"GET"},
     *     path="/blackfire/index"
     * )
     *
     * @throws \Exception
     */
    public function indexAction(): Response
    {
        return new JsonResponse([
            'started' => \BlackfireProbe::wasStarted(), /* @phpstan-ignore-line */
            'stopped' => \BlackfireProbe::wasStopped(), /* @phpstan-ignore-line */
        ]);
    }
}
