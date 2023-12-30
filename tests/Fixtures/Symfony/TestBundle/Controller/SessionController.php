<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route as RouteAnnotation;
use Symfony\Component\Routing\Attribute\Route;

final class SessionController
{
    /**
     * @RouteAnnotation(methods={"GET"}, path="/session")
     * @RouteAnnotation(methods={"GET"}, path="/session/1")
     * @RouteAnnotation(methods={"GET"}, path="/session/2")
     *
     * @throws \Exception
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
}
