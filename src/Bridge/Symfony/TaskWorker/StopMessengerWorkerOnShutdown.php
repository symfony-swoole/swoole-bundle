<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\TaskWorker;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

/**
 * EXPERIMENTAL. Stops a messenger worker between messages when the server is shutting down.
 *
 * This is the cooperative half of the stop path, and the only half that works with coroutines off:
 * there, nothing else in the task worker is running, so no watchdog exists to hand the command a signal
 * and the command has to notice for itself. WorkerRunningEvent fires between messages, which is exactly
 * where stopping is safe - the message in flight is finished and acked first.
 *
 * Harmless with coroutines on, where the watchdog usually gets there first; whichever notices first
 * calls the same Worker::stop().
 *
 * Registered only when symfony/messenger is installed.
 */
final readonly class StopMessengerWorkerOnShutdown implements EventSubscriberInterface
{
    public function __construct(private WorkerStopSignal $stopSignal) {}

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [WorkerRunningEvent::class => 'onWorkerRunning'];
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if (!$this->stopSignal->isRaised()) {
            return;
        }

        $event->getWorker()
            ->stop();
    }
}
