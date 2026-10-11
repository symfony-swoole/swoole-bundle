<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\HttpFoundation\Session\PdoSession;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Keeps what it is told, for a test to read.
 */
final class RecordingLogger extends AbstractLogger
{
    /**
     * @var list<string>
     */
    private array $messages = [];

    /**
     * @param array<mixed> $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->messages[] = (string) $message;
    }

    /**
     * @return list<string>
     */
    public function messages(): array
    {
        return $this->messages;
    }
}
