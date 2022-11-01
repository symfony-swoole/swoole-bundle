<?php

declare(strict_types=1);

namespace K911\Swoole\Component\Locking;

final class Store
{
    /**
     * @var array<string, int>
     */
    private array $locks = [];

    public function save(string $key, int $lockId): void
    {
        if ($this->has($key)) {
            throw new \RuntimeException(sprintf('Lock was already acquired for key %s.', $key));
        }

        $this->locks[$key] = $lockId;
    }

    public function delete(string $key): void
    {
        if (!$this->has($key)) {
            throw new \RuntimeException(sprintf('Lock key %s does not exist.', $key));
        }

        unset($this->locks[$key]);
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
