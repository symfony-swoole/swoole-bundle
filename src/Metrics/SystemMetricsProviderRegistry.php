<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Metrics;

use RuntimeException;
use SwooleBundle\SwooleBundle\Common\System\System;
use Traversable;

final class SystemMetricsProviderRegistry
{
    /**
     * @var array<string, MetricsProvider>
     */
    private array $metricsProviders;

    /**
     * @param Traversable<string, MetricsProvider> $metricsProviders
     */
    public function __construct(
        private readonly System $system,
        Traversable $metricsProviders,
    ) {
        $this->metricsProviders = iterator_to_array($metricsProviders);
    }

    public function get(): MetricsProvider
    {
        $extensionString = $this->system->extension()->toString();

        if (!isset($this->metricsProviders[$extensionString])) {
            throw new RuntimeException(sprintf('Metrics provider for extension "%s" not found.', $extensionString));
        }

        return $this->metricsProviders[$extensionString];
    }
}
