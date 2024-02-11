<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component;

use Exception;
use Generator;
use IteratorAggregate;

/**
 * @template T
 * @implements IteratorAggregate<T>
 */
final class GeneratedCollection implements IteratorAggregate
{
    private $items;

    /**
     * @param iterable<T> $itemCollection
     * @param T ...$items
     */
    public function __construct(
        private readonly iterable $itemCollection,
        ...$items,
    ) {
        $this->items = $items;
    }

    /**
     * @template U
     * @param callable(T): U $func
     * @return Generator<U>
     * @throws Exception
     */
    public function each(callable $func): Generator
    {
        foreach ($this->getIterator() as $item) {
            yield $func($item);
        }
    }

    /**
     * @template U
     * @param callable(T): U $func
     * @return GeneratedCollection<U>
     * @throws Exception
     */
    public function map(callable $func): self
    {
        return new self($this->each($func));
    }

    /**
     * @return GeneratedCollection<T>
     * @throws Exception
     */
    public function filter(callable $func): self
    {
        return new self($this->filterItems($func));
    }

    /**
     * @return Generator<T>
     */
    public function getIterator(): Generator
    {
        yield from $this->itemCollection;

        yield from $this->items;
    }

    /**
     * @return Generator<T>
     * @throws Exception
     */
    private function filterItems(callable $func): Generator
    {
        foreach ($this->getIterator() as $item) {
            if (!$func($item)) {
                continue;
            }

            yield $item;
        }
    }
}
