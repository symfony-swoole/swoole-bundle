<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

final class StatefulServiceTag
{
    /**
     * @param array{limit?: int, resetter?: string, reset_on_each_request?: bool} $tag
     */
    public function __construct(private array $tag)
    {
    }

    public function getLimit(): ?int
    {
        return $this->tag['limit'] ?? null;
    }

    public function getResetter(): ?string
    {
        return $this->tag['resetter'] ?? null;
    }

    public function getResetOnEachRequest(): ?bool
    {
        return $this->tag['reset_on_each_request'] ?? null;
    }
}
