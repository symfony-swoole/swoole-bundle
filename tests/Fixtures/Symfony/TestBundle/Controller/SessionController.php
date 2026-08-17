<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use DateTimeImmutable;
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
     * Puts an object in the session and reports what comes back out of it on a later request.
     *
     * A session bag takes whatever the application gives it, and Symfony's own security component
     * relies on that - a failed login is left in the session as an AuthenticationException for the
     * login page to read. A store that cannot carry an object hands back something of another type
     * without failing anywhere along the way.
     */
    #[Route(path: '/session/object', methods: ['GET'])]
    public function objectRoundTrip(SessionInterface $session): JsonResponse
    {
        if (!$session->has('storedObject')) {
            $session->set('storedObject', new DateTimeImmutable('2020-02-02T02:02:02+00:00'));

            return new JsonResponse(['stored' => true]);
        }

        $stored = $session->get('storedObject');

        return new JsonResponse([
            'stored' => false,
            'type' => get_debug_type($stored),
            'value' => $stored instanceof DateTimeImmutable ? $stored->format(DATE_ATOM) : null,
        ]);
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
