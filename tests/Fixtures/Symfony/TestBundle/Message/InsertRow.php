<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Message;

/**
 * Asks for one row to be written, carrying the id the sender gave it.
 *
 * The id is what makes a lost or a twice-handled message visible afterwards: the rows are counted
 * against the ids that were sent rather than against each other.
 */
final readonly class InsertRow
{
    public function __construct(
        private string $messageId,
        private int $sleepMs = 0,
    ) {}

    public function messageId(): string
    {
        return $this->messageId;
    }

    /**
     * How long the handler should hold its consumer up for.
     *
     * Enough work to overlap is the point of it. Handled instantly, one consumer of a group can drain
     * a queue before its siblings have finished their first poll, and a test meant to exercise four
     * of them at once would exercise one.
     */
    public function sleepMs(): int
    {
        return $this->sleepMs;
    }
}
