<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class SessionResetEvent extends Event
{
    public const NAME = 'SWOOLE.SESSION.STORAGE_RESET';

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
