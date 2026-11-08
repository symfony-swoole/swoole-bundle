<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A request that holds a pooled connection for a while: it opens its session with a query, then sleeps, so requests
 * sent at the same moment each hold a connection of their own at the same time. A coroutine gives its connection back
 * to the pool only as it ends, after the response.
 */
final readonly class HeldConnectionController
{
    private const int HOLD_MICROSECONDS = 300_000;

    public function __construct(private Connection $connection) {}

    #[Route(path: '/doctrine-hold', methods: ['GET'])]
    public function hold(): Response
    {
        $sessionId = $this->connection->fetchOne('SELECT CONNECTION_ID()');
        usleep(self::HOLD_MICROSECONDS);

        return new Response(is_scalar($sessionId) ? (string) $sessionId : '');
    }
}
