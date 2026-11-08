<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

final class StatefulServiceTag
{
    /**
     * @param array{
     *     limit?: int,
     *     idle_limit?: int,
     *     resetter?: string,
     *     initializer?: string,
     *     reset_on_each_request?: bool,
     * } $tag
     */
    public function __construct(private array $tag) {}

    public function getLimit(): ?int
    {
        return $this->tag['limit'] ?? null;
    }

    /**
     * How many of the instances given back the service's pool keeps for the next coroutines; the rest are disposed of.
     */
    public function getIdleLimit(): ?int
    {
        return $this->tag['idle_limit'] ?? null;
    }

    public function getResetter(): ?string
    {
        return $this->tag['resetter'] ?? null;
    }

    public function getInitializer(): ?string
    {
        return $this->tag['initializer'] ?? null;
    }

    public function getResetOnEachRequest(): ?bool
    {
        return $this->tag['reset_on_each_request'] ?? null;
    }
}
