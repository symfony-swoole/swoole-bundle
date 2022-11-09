<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

final class UnmanagedFactoryTag
{
    /**
     * @var array{factoryMethod: string, returnType?: class-string|string, limit?: int}
     */
    private array $tag;

    /**
     * @param array{factoryMethod: string, returnType?: class-string|string, limit?: int} $tag
     */
    public function __construct(array $tag)
    {
        $this->tag = $tag;
    }

    public function getFactoryMethod(): string
    {
        return $this->tag['factoryMethod'];
    }

    /**
     * @return class-string|string
     */
    public function getReturnType(): ?string
    {
        return $this->tag['returnType'] ?? null;
    }

    public function getLimit(): ?int
    {
        return $this->tag['limit'] ?? null;
    }
}
