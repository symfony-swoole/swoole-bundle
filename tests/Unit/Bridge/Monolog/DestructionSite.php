<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Monolog;

use Swoole\Coroutine;

/**
 * Where a {@see DestructionTripwire} reports back to, since the tripwire itself is gone by the time the
 * answer matters. Holds both halves of the question: which coroutine logged, and which one let go.
 */
final class DestructionSite
{
    private ?int $loggedIn = null;

    private ?int $destroyedIn = null;

    public function noteTheCoroutineLoggingHere(): void
    {
        $this->loggedIn = self::currentCoroutine();
    }

    public function noteTheCoroutineReleasingHere(): void
    {
        $this->destroyedIn = self::currentCoroutine();
    }

    public function loggedIn(): ?int
    {
        return $this->loggedIn;
    }

    public function destroyedIn(): ?int
    {
        return $this->destroyedIn;
    }

    /**
     * Null outside a coroutine, which the engine answers -1 for.
     */
    private static function currentCoroutine(): ?int
    {
        $cid = (int) Coroutine::getCid();

        return $cid > 0 ? $cid : null;
    }
}
