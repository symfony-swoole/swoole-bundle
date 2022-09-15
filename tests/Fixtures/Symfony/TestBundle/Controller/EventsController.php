<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Controller;

use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\EventHandler\LifecycleEventsEventHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final class EventsController
{
    private LifecycleEventsEventHandler $eventHandler;

    public function __construct(LifecycleEventsEventHandler $eventHandler)
    {
        $this->eventHandler = $eventHandler;
    }

    /**
     * @Route(
     *     methods={"GET"},
     *     path="/list-events"
     * )
     */
    public function listEvents(): JsonResponse
    {
        return new JsonResponse(
            [
                'serverStarted' => $this->eventHandler->isServerStarted(),
                'workerStarted' => $this->eventHandler->isWorkerStarted(),
            ],
            200
        );
    }
}
