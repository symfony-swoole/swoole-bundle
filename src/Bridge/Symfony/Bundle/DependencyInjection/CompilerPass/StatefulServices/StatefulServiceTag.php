<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

final class StatefulServiceTag
{
    /**
     * @var array{limit?: int, resetter?: string}
     */
    private array $tag;

    /**
     * @param array{limit?: int, resetter?: string} $tag
     */
    public function __construct(array $tag)
    {
        $this->tag = $tag;
    }

    public function getLimit(): ?int
    {
        return $this->tag['limit'] ?? null;
    }

    public function getResetter(): ?string
    {
        return $this->tag['resetter'] ?? null;
    }
}
