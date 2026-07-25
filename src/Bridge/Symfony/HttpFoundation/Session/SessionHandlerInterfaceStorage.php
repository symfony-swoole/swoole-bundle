<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session;

use Assert\Assertion;
use Assert\AssertionFailedException;
use SessionHandlerInterface;
use SwooleBundle\SwooleBundle\Server\Session\Exception\LogicException;
use SwooleBundle\SwooleBundle\Server\Session\Storage;

/**
 * Adapter that exposes any generic PHP SessionHandlerInterface as the bundle's internal Storage.
 *
 * This is useful for reusing Symfony session handlers such as PdoSessionHandler.
 *
 * Lifecycle note: this bundle does not model a persistent "session lifecycle" the way native PHP
 * does, so every Storage operation is treated as a self-contained unit of work. Each method opens
 * the handler (with an empty save path and the configured session name), performs its single
 * read/write/destroy operation, and then closes it again. As a consequence, any locking strategy
 * in the underlying handler (e.g. PdoSessionHandler::LOCK_TRANSACTIONAL) is only held for the
 * duration of that one Storage call, not across a whole read-then-write session cycle.
 *
 * Known limitations:
 *  - TTL is ignored on set(). A generic SessionHandlerInterface has no per-write TTL parameter;
 *    expiry is the responsibility of the underlying handler (e.g. PdoSessionHandler's ttl option
 *    or PHP's session.gc_maxlifetime ini setting).
 *  - get() cannot distinguish "missing" from "expired", so it never invokes the $expired callback
 *    and never performs proactive regeneration. An empty/false read is simply returned as null.
 *  - count() is not supported because SessionHandlerInterface provides no counting capability.
 *
 * @experimental
 */
final readonly class SessionHandlerInterfaceStorage implements Storage
{
    public function __construct(
        private SessionHandlerInterface $handler,
        private string $sessionName = SwooleSessionStorage::DEFAULT_SESSION_NAME,
    ) {}

    /**
     * @throws AssertionFailedException
     */
    public function set(string $key, mixed $data, int $ttl): void
    {
        Assertion::string($data, 'Storage data expected to be string, type %2$s given.');

        $this->withOpenHandler(function () use ($key, $data): void {
            $this->handler->write($key, $data);
        });
    }

    public function delete(string $key): void
    {
        $this->withOpenHandler(function () use ($key): void {
            $this->handler->destroy($key);
        });
    }

    public function garbageCollect(): void
    {
        $this->withOpenHandler(function (): void {
            $this->handler->gc((int) ini_get('session.gc_maxlifetime'));
        });
    }

    public function count(): int
    {
        throw new LogicException(
            'Counting sessions is not supported when using a SessionHandlerInterface-backed storage.'
        );
    }

    public function get(string $key, ?callable $expired = null): mixed
    {
        return $this->withOpenHandler(function () use ($key, $expired): ?string {
            $data = $this->handler->read($key);

            if (
                $expired !== null &&
                method_exists($this->handler, 'isSessionExpired') &&
                $this->handler->isSessionExpired()
            ) {
                $expired($key, $data);
            }

            if ($data === false || $data === '') {
                return null;
            }

            return $data;
        });
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withOpenHandler(callable $callback): mixed
    {
        $this->handler->open('', $this->sessionName);

        try {
            return $callback();
        } finally {
            $this->handler->close();
        }
    }
}
