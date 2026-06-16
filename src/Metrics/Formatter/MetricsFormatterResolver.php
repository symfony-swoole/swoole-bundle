<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Metrics\Formatter;

use Traversable;

/**
 * Resolves the metrics formatter to use based on the request "Accept" header,
 * falling back to a default formatter when none matches.
 */
final readonly class MetricsFormatterResolver
{
    /**
     * @var array<MetricsFormatter>
     */
    private array $formatters;

    /**
     * @param Traversable<MetricsFormatter> $formatters
     */
    public function __construct(
        Traversable $formatters,
        private MetricsFormatter $default,
    ) {
        $this->formatters = iterator_to_array($formatters, false);
    }

    public function resolve(string $accept): MetricsFormatter
    {
        foreach ($this->formatters as $formatter) {
            if ($formatter->supports($accept)) {
                return $formatter;
            }
        }

        return $this->default;
    }
}
