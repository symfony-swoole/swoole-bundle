<?php

declare(strict_types=1);

namespace K911\Swoole\Component\Locking;

final class Store
{
    /**
     * @var array<string, int>
     */
    private array $locks = [];

    private array $counts = [];

    private array $realLocks = [];

    public function save(string $key, int $lockId, ?Lock $lock = null): Lock
    {
        if ($this->has($key)) {
            ++$this->counts[$key];

            return $this->realLocks[$key];
        }

        $this->locks[$key] = $lockId;
        $this->counts[$key] = 1;

        if (null === $lock) {
            $lock = new CoroutineLock($key, $this);
        }

        return $this->realLocks[$key] = $lock;
    }

    public function delete(string $key): void
    {
        if (!$this->has($key)) {
            throw new \RuntimeException(sprintf('Lock key %s does not exist.', $key));
        }

        --$this->counts[$key];

        if ($this->counts[$key] > 0) {
            return;
        }

        unset($this->locks[$key], $this->counts[$key], $this->realLocks[$key]);
    }

    public function has(string $key): bool
    {
        return isset($this->locks[$key]);
    }

    public function get(string $key): int
    {
        if (!$this->has($key)) {
            throw new \RuntimeException(sprintf('Lock key %s does not exist.', $key));
        }

        return $this->locks[$key];
    }
}
