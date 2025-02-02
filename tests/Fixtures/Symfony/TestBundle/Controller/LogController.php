<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Logging\InMemoryLogger;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LogController
{
    #[Route(path: '/logs', methods: ['GET'])]
    public function getLogs(): Response
    {
        return new Response(implode(PHP_EOL, InMemoryLogger::getAndClear()), 200);
    }
}
