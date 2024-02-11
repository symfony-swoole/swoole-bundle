<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Session;

interface Storage
{
    /**
     * Add or update session storage storage by key.
     *
     * @param int $ttl lifetime in seconds
     */
    public function set(string $key, mixed $data, int $ttl): void;

    /**
     * Delete session storage by key.
     */
    public function delete(string $key): void;

    /**
     * Invalidate all expired session storage.
     */
    public function garbageCollect(): void;

    /**
     * Get session storage data by key.
     *
     * @param callable(string $key, mixed $data):void|null $expired What to do when key has expired
     *                                                              (for example: delete data)
     * @return mixed|null data Should return the same type as provided in set(),
     *                    null when data is not available or expired
     */
    public function get(string $key, ?callable $expired = null): mixed;
}
