<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Controller;

use Symfony\Component\Routing\Annotation\Route as RouteAnnotation;
use Symfony\Component\Routing\Attribute\Route;

final class ThrowableController
{
    /**
     * @RouteAnnotation(
     *     methods={"GET"},
     *     path="/throwable/error"
     * )
     */
    #[Route(path: '/throwable/error', methods: ['GET'])]
    public function error(): void
    {
        try {
            throw new \Exception('Previous', 5001);
        } catch (\Throwable $exception) {
            throw new \Error('Critical failure', 5000, $exception);
        }
    }

    /**
     * @RouteAnnotation(
     *     methods={"GET"},
     *     path="/throwable/exception"
     * )
     */
    #[Route(path: '/throwable/exception', methods: ['GET'])]
    public function exception(): never
    {
        throw new \RuntimeException('An exception has occurred', 5000);
    }
}
