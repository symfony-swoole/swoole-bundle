<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use Psr\Log\LoggerInterface;
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

    /**
     * Writes one record through the real monolog stack, at a level the file handler actually accepts.
     *
     * The marker is echoed back so a caller can tell what it asked for from what ended up on disk - the
     * point of the route is being able to count records, not the message itself.
     */
    #[Route(path: '/log-warning/{marker}', methods: ['GET'])]
    public function logWarning(string $marker, LoggerInterface $logger): Response
    {
        $logger->warning(sprintf('Coroutine logging test: %s', $marker));

        return new Response($marker, 200);
    }
}
