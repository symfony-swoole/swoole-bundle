<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Coroutine;

use Override;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;

/**
 * A mutex that keeps what it was asked for, and which coroutine was running as it was asked.
 *
 * It says it was released before it releases the one it wraps, because releasing a mutex of coroutines runs whoever
 * was waiting for it there and then - so what it kept would otherwise read as the waiter holding it before the one
 * that let go had let go.
 */
final class RecordingMutex implements Mutex
{
    /**
     * @var list<array{string, int}> what happened, and in which coroutine
     */
    private array $said = [];

    public function __construct(
        private readonly Mutex $wrapped,
        private readonly Swoole $swoole,
    ) {}

    /**
     * @return list<array{string, int}>
     */
    public function said(): array
    {
        return $this->said;
    }

    #[Override]
    public function acquire(): void
    {
        $this->wrapped->acquire();

        $this->said[] = ['held', $this->swoole->getCoroutineId()];
    }

    #[Override]
    public function release(): void
    {
        $this->said[] = ['released', $this->swoole->getCoroutineId()];

        $this->wrapped->release();
    }

    #[Override]
    public function isAcquired(): bool
    {
        return $this->wrapped->isAcquired();
    }
}
