<?php

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation;

use Psr\Log\LoggerInterface;
use SwooleBundle\SwooleBundle\Bridge\Log\AccessLogFormatter;
use SwooleBundle\SwooleBundle\Bridge\Log\SymfonyAccessLogDataMap;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class AccessLogOnKernelTerminate implements EventSubscriberInterface
{
    private SymfonyAccessLogDataMap $symfonyAccessLogDataMap;

    public function __construct(
        private LoggerInterface $accessLogLogger,
        private AccessLogFormatter $formatter,
    ) {
        $this->symfonyAccessLogDataMap = new SymfonyAccessLogDataMap();
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $message = $this->formatter->format(
            $this->symfonyAccessLogDataMap->setRequestResponse($event->getRequest(), $event->getResponse())
        );
        $this->accessLogLogger->info($message);
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => ['onKernelTerminate', -2048],
        ];
    }
}
