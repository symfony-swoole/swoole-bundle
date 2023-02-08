<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

final class SafeStatefulServiceTag
{
    /**
     * @param array{reset_on_each_request?: bool} $tag
     */
    public function __construct(private array $tag)
    {
    }

    public function getResetOnEachRequest(): ?bool
    {
        return $this->tag['reset_on_each_request'] ?? null;
    }
}
