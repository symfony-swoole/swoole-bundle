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
     * Keep the data stored by key for another lifetime, without writing it again.
     *
     * For a session the request read and left as it was. Writing it back would put the request's copy over whatever
     * a request of the same session stored meanwhile - a CSRF token, say - so it is only kept from expiring.
     * Nothing is stored where nothing is under the key.
     *
     * @param mixed $data what the session holds now, for a store that can only keep it by writing it again
     * @param int $ttl lifetime in seconds
     */
    public function touch(string $key, mixed $data, int $ttl): void;

    /**
     * Delete session storage by key.
     */
    public function delete(string $key): void;

    /**
     * Invalidate all expired session storage.
     */
    public function garbageCollect(): void;

    /**
     * Return the number of sessions currently in storage.
     */
    public function count(): int;

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
