<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

final class UnmanagedFactoryTag
{
    /**
     * @param array{
     *     factoryMethod: string,
     *     returnType?: class-string|string,
     *     limit?: int,
     *     resetter?: string
     * } $tag
     */
    public function __construct(private array $tag)
    {
    }

    public function getFactoryMethod(): string
    {
        return $this->tag['factoryMethod'];
    }

    /**
     * @return null|class-string|string
     */
    public function getReturnType(): ?string
    {
        return $this->tag['returnType'] ?? null;
    }

    public function getLimit(): ?int
    {
        return $this->tag['limit'] ?? null;
    }

    public function getResetter(): ?string
    {
        return $this->tag['resetter'] ?? null;
    }

    /**
     * @return array{
     *     factoryMethod: string,
     *     returnType?: class-string|string,
     *     limit?: int
     * }
     */
    public function toArray(): array
    {
        return $this->tag;
    }
}
