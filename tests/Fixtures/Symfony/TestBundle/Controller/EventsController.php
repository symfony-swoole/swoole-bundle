<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\EventHandler\LifecycleEventsEventHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route as RouteAnnotation;
use Symfony\Component\Routing\Attribute\Route;

final class EventsController
{
    public function __construct(private readonly LifecycleEventsEventHandler $eventHandler)
    {
    }

    /**
     * @RouteAnnotation(
     *     methods={"GET"},
     *     path="/list-events"
     * )
     */
    #[Route(path: '/list-events', methods: ['GET'])]
    public function listEvents(): JsonResponse
    {
        return new JsonResponse(
            [
                'serverStarted' => $this->eventHandler->isServerStarted(),
                'workerStarted' => $this->eventHandler->isWorkerStarted(),
                'workerStopped' => $this->eventHandler->isWorkerStopped(),
                'workerExited' => $this->eventHandler->isWorkerExited(),
                'workerError' => $this->eventHandler->isWorkerError(),
            ],
            200
        );
    }
}
