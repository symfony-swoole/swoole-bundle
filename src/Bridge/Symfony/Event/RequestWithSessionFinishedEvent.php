<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class RequestWithSessionFinishedEvent extends Event
{
    public const NAME = 'swoole_bundle.request.with.session.finished';

    private string $sessionId;

    public function __construct(string $sessionId)
    {
        $this->sessionId = $sessionId;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }
}
