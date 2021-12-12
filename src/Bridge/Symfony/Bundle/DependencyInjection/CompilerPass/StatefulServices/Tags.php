<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use ArrayIterator;
use IteratorAggregate;
use K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use RuntimeException;
use Traversable;
use UnexpectedValueException;

/**
 * @template-implements IteratorAggregate<string, array<array<string, mixed>>>
 */
final class Tags implements IteratorAggregate
{
    /**
     * @var class-string
     */
    private string $serviceClass;

    /**
     * @var array<string, array<array<string, mixed>>>
     */
    private array $tags;

    /**
     * @param class-string                               $serviceClass
     * @param array<string, array<array<string, mixed>>> $tags
     */
    public function __construct(string $serviceClass, array $tags)
    {
        $this->serviceClass = $serviceClass;
        $this->tags = $tags;
    }

    public function hasStatefulServiceTag(): bool
    {
        $ufTags = $this->findByName(ContainerConstants::TAG_STATEFUL_SERVICE);

        return !empty($ufTags);
    }

    public function hasDecoratedStatefulServiceTag(): bool
    {
        $ufTags = $this->findByName(ContainerConstants::TAG_DECORATED_STATEFUL_SERVICE);

        return !empty($ufTags);
    }

    public function hasSafeStatefulServiceTag(): bool
    {
        $ufTags = $this->findByName(ContainerConstants::TAG_SAFE_STATEFUL_SERVICE);

        return !empty($ufTags);
    }

    public function findUnmanagedFactoryTags(): ?UnmanagedFactoryTags
    {
        /** @var array<array{factoryMethod: string, returnType?: class-string|string}> $ufTags */
        $ufTags = $this->getByName(ContainerConstants::TAG_UNMANAGED_FACTORY);

        if (empty($ufTags)) {
            return null;
        }

        return new UnmanagedFactoryTags($this->serviceClass, $ufTags);
    }

    public function getUnmanagedFactoryTags(): UnmanagedFactoryTags
    {
        $ufTags = $this->findUnmanagedFactoryTags();

        if (null === $ufTags) {
            throw new RuntimeException(sprintf('No unmanaged factory tags exist for class %s', $this->serviceClass));
        }

        return $ufTags;
    }

    /**
     * @return Traversable<string, array<array<string, mixed>>>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->tags);
    }

    /**
     * @return array<array<array<string, mixed>>>
     */
    private function findByName(string $name): array
    {
        return $this->tags[$name] ?? [];
    }

    /**
     * @return array<array<array<string, mixed>>>
     */
    private function getByName(string $name): array
    {
        $found = $this->findByName($name);

        if (empty($found)) {
            throw new UnexpectedValueException(sprintf('Found 0 tags of name %s, at least 1 was expected.', $name));
        }

        return $found;
    }
}
