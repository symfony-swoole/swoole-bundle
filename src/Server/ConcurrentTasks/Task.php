<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\ConcurrentTasks;

use Closure;
use UnexpectedValueException;

use function Opis\Closure\serialize as opis_serialize;
use function Opis\Closure\unserialize as opis_unserialize;

final class Task
{
    public function __construct(
        private Closure $callback,
    ) {}

    /**
     * @return array{callback: string}
     */
    public function __serialize(): array
    {
        return [
            'callback' => opis_serialize($this->callback),
        ];
    }

    /**
     * @param array{callback: string} $data
     * @phpstan-param array{callback: string} $data
     */
    public function __unserialize(array $data): void
    {
        $callback = opis_unserialize($data['callback']);

        if (!$callback instanceof Closure) {
            throw new UnexpectedValueException('Unserialized callback is not a Closure');
        }

        $this->callback = $callback;
    }

    public static function create(Closure $callback): self
    {
        return new self($callback);
    }

    public function getCallback(): Closure
    {
        return $this->callback;
    }
}
