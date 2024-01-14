<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

use IteratorAggregate;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;

/**
 * @template-implements IteratorAggregate<string, array<array<string, mixed>>>
 */
final class Tags implements \IteratorAggregate
{
    /**
     * @param class-string                               $serviceClass
     * @param array<string, array<array<string, mixed>>> $tags
     */
    public function __construct(
        private readonly string $serviceClass,
        private array $tags
    ) {
    }

    public function hasStatefulServiceTag(): bool
    {
        $ufTags = $this->findByName(ContainerConstants::TAG_STATEFUL_SERVICE);

        return !empty($ufTags);
    }

    public function findStatefulServiceTag(): ?StatefulServiceTag
    {
        $ssTags = $this->findByName(ContainerConstants::TAG_STATEFUL_SERVICE);

        if (empty($ssTags)) {
            return null;
        }

        /* @phpstan-ignore-next-line */
        return new StatefulServiceTag($ssTags[0]);
    }

    public function findSafeStatefulServiceTag(): ?SafeStatefulServiceTag
    {
        $ssTags = $this->findByName(ContainerConstants::TAG_SAFE_STATEFUL_SERVICE);

        if (empty($ssTags)) {
            return null;
        }

        /* @phpstan-ignore-next-line */
        return new SafeStatefulServiceTag($ssTags[0]);
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
            throw new \RuntimeException(sprintf('No unmanaged factory tags exist for class %s', $this->serviceClass));
        }

        return $ufTags;
    }

    /**
     * @return \Traversable<string, array<array<string, mixed>>>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->tags);
    }

    public function resetOnEachRequest(): bool
    {
        $safeSsTag = $this->findSafeStatefulServiceTag();

        if ($safeSsTag && $safeSsTag->getResetOnEachRequest()) {
            return true;
        }

        $sStag = $this->findStatefulServiceTag();

        if ($sStag && $sStag->getResetOnEachRequest()) {
            return true;
        }

        return false;
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
            throw new \UnexpectedValueException(sprintf('Found 0 tags of name %s, at least 1 was expected.', $name));
        }

        return $found;
    }
}
