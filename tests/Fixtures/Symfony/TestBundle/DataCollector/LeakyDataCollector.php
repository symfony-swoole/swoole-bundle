<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\DataCollector;

use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Throwable;

/**
 * Minimal stand-in for a real Symfony data collector that holds its own accumulator state
 * on top of $this->data (like RequestDataCollector::$sessionUsages or RequestDataCollector::$controllers):
 * collect() itself is well-behaved (it fully overwrites $this->data, same as every core collector), but
 * $entries only ever gets cleared by reset() - so if this collector is pooled by swoole-bundle without its
 * reset() surviving, $entries keeps growing across every request that reuses the pooled instance.
 */
final class LeakyDataCollector extends DataCollector
{
    /**
     * @var array<string>
     */
    private array $entries = [];

    public function record(string $entry): void
    {
        $this->entries[] = $entry;
    }

    #[Override]
    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $this->data = ['count' => count($this->entries)];
    }

    public function getCount(): int
    {
        return count($this->entries);
    }

    #[Override]
    public function getName(): string
    {
        return 'leaky';
    }

    #[Override]
    public function reset(): void
    {
        parent::reset();

        $this->entries = [];
    }
}
