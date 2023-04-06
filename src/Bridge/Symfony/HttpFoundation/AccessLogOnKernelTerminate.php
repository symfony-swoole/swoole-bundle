<?php

namespace K911\Swoole\Bridge\Symfony\HttpFoundation;

use K911\Swoole\Bridge\Log\AccessLogDataMap;
use K911\Swoole\Bridge\Log\AccessLogFormatterInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AccessLogOnKernelTerminate implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $accessLogLogger,
        private AccessLogFormatterInterface $formatter,
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
