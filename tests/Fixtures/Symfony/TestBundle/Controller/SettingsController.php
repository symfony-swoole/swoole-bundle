<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use SwooleBundle\SwooleBundle\Server\HttpServer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final readonly class SettingsController
{
    public function __construct(
        private HttpServer $httpServer,
    ) {}

    #[Route(path: '/settings', methods: ['GET'])]
    public function index(): Response
    {
        return new JsonResponse($this->httpServer->getServer()->setting);
    }
}
