<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use Exception;
use SwooleBundle\SwooleBundle\Server\Session\Storage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class SessionController
{
    /**
      * @throws Exception
      */
    #[Route(path: '/session', methods: ['GET'])]
    #[Route(path: '/session/1', methods: ['GET'])]
    #[Route(path: '/session/2', methods: ['GET'])]
    public function index(SessionInterface $session): JsonResponse
    {
        if (!$session->has('luckyNumber')) {
            $session->set('luckyNumber', random_int(1, 1_000_000));
        }

        $metadata = $session->getMetadataBag();

        return new JsonResponse([
            'hello' => 'world!',
            'sessionMetadata' => [
                'created_at' => $metadata->getCreated(),
                'updated_at' => $metadata->getLastUsed(),
                'lifetime' => $metadata->getLifetime(),
            ],
            'luckyNumber' => $session->get('luckyNumber'),
        ], 200);
    }

    /**
     * Stores a payload intentionally larger than the session storage limit.
     * Used by tests that verify the max_data_bytes boundary is enforced.
     *
     * @throws Exception
     */
    #[Route(path: '/session/large', methods: ['GET'])]
    public function storeLargePayload(SessionInterface $session): JsonResponse
    {
        $session->set('bigData', str_repeat('x', 600));

        return new JsonResponse(['stored' => true], 200);
    }

    /**
     * Used by garbage collection tests.
     *
     * @throws Exception
     */
    #[Route(path: '/session/gc-test', methods: ['GET'])]
    public function gcTest(SessionInterface $session): JsonResponse
    {
        $session->set('gc_test', time());

        return new JsonResponse(['status' => 'session created'], 200);
    }

    /**
     * Returns the number of sessions currently in the SwooleTableStorage — for GC integration tests only.
     */
    #[Route(path: '/session/gc-count', methods: ['GET'])]
    public function gcCount(Storage $storage): JsonResponse
    {
        return new JsonResponse(['count' => $storage->count()]);
    }
}
