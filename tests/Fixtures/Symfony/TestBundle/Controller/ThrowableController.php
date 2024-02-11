<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use Error;
use Exception;
use RuntimeException;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

final class ThrowableController
{
    #[Route(path: '/throwable/error', methods: ['GET'])]
    public function error(): void
    {
        try {
            throw new Exception('Previous', 5001);
        } catch (Throwable $exception) {
            throw new Error('Critical failure', 5000, $exception);
        }
    }

    #[Route(path: '/throwable/exception', methods: ['GET'])]
    public function exception(): never
    {
        throw new RuntimeException('An exception has occurred', 5000);
    }
}
