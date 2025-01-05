<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation;

use Assert\Assertion;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\StreamedResponseListener as HttpFoundationStreamedResponseListener;
use Symfony\Component\HttpKernel\KernelEvents;

final class StreamedResponseListener implements EventSubscriberInterface
{
    public function __construct(private readonly ?HttpFoundationStreamedResponseListener $delegate = null) {}

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $attributes = $event->getRequest()->attributes;

        if (
            $response instanceof StreamedResponse
            && $attributes->has(ResponseProcessorInjector::ATTR_KEY_RESPONSE_PROCESSOR)
        ) {
            $processor = $attributes->get(ResponseProcessorInjector::ATTR_KEY_RESPONSE_PROCESSOR);
            Assertion::isCallable($processor);
            $processor($response);

            return;
        }

        if ($this->delegate === null) {
            return;
        }

        $this->delegate->onKernelResponse($event);
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
        ];
    }
}
