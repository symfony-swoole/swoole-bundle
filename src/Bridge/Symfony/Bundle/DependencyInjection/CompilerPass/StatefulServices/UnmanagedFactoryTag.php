<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

final class UnmanagedFactoryTag
{
    /**
     * @var array{factoryMethod: string, returnType?: class-string|string}
     */
    private array $tag;

    /**
     * @param array{factoryMethod: string, returnType?: class-string|string} $tag
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
}
