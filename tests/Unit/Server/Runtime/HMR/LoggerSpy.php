<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Runtime\HMR;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

final class LoggerSpy extends AbstractLogger
{
    /**
     * @var list<string>
     */
    private array $messages = [];

    /**
     * @param array<string, mixed> $context
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->messages[] = (string) $message;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->messages;
    }
}
