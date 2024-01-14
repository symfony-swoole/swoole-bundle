<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class RequestWithSessionFinishedEvent extends Event
{
    public const NAME = 'swoole_bundle.request.with.session.finished';

    public function __construct(private readonly string $sessionId)
    {
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }
}
