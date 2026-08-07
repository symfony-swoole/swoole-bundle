<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Mimics the shape of Symfony's own kernel.reset-tagged debug/traceable services
 * (Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder, Symfony\Component\Cache\Adapter\TraceableAdapter,
 * Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher, ...): an append-only log that only reset()
 * ever clears. Framework code assumes reset() runs once per request for any service tagged kernel.reset;
 * this fixture is used to prove whether that assumption still holds once the service is pooled.
 */
final class LeakyResource implements ResetInterface
{
    /**
     * @var array<string>
     */
    private array $entries = [];

    public function record(string $entry): void
    {
        $this->entries[] = $entry;
    }

    /**
     * @return array<string>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function reset(): void
    {
        $this->entries = [];
    }
}
