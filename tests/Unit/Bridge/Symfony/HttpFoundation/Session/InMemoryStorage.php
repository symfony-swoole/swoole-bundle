<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\HttpFoundation\Session;

use SwooleBundle\SwooleBundle\Server\Session\Storage;

/**
 * A store that keeps what it is given and says what it was asked to do.
 */
final class InMemoryStorage implements Storage
{
    /**
     * @var array<string, mixed>
     */
    private array $stored = [];

    /**
     * @var list<string>
     */
    private array $writes = [];

    /**
     * @var list<string>
     */
    private array $touches = [];

    /**
     * @return array<string, mixed>
     */
    public function stored(): array
    {
        return $this->stored;
    }

    /**
     * @return list<string> the ids written, in order
     */
    public function writes(): array
    {
        return $this->writes;
    }

    /**
     * @return list<string> the ids touched, in order
     */
    public function touches(): array
    {
        return $this->touches;
    }

    /**
     * A session stored before the request under test: what that request asks comes after.
     */
    public function forgetWhatWasAsked(): void
    {
        $this->writes = [];
        $this->touches = [];
    }

    public function set(string $key, mixed $data, int $ttl): void
    {
        $this->stored[$key] = $data;
        $this->writes[] = $key;
    }

    public function touch(string $key, mixed $data, int $ttl): void
    {
        $this->touches[] = $key;
    }

    public function delete(string $key): void
    {
        unset($this->stored[$key]);
    }

    public function garbageCollect(): void {}

    public function count(): int
    {
        return count($this->stored);
    }

    public function get(string $key, ?callable $expired = null): mixed
    {
        return $this->stored[$key] ?? null;
    }
}
