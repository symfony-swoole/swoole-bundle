<?php

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation;

use Psr\Log\LoggerInterface;
use SwooleBundle\SwooleBundle\Bridge\Log\AccessLogDataMap;
use SwooleBundle\SwooleBundle\Bridge\Log\AccessLogFormatterInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AccessLogOnKernelTerminate implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $accessLogLogger,
        private readonly AccessLogFormatterInterface $formatter,
    ) {
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $message = $this->formatter->format(new AccessLogDataMap($event->getRequest(), $event->getResponse()));
        $this->accessLogLogger->info($message);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => ['onKernelTerminate', -2048],
        ];
    }
}
