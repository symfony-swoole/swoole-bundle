<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Messenger;

/**
 * What {@see MessengerProcessor} decided about one messenger transport, and why.
 *
 * The why is the whole reason this is a type rather than a nullable class name. Working out whether a
 * transport can be pooled asks half a dozen questions of it, any of which can answer no, and by the
 * time the answer reaches the caller a bare null has forgotten which one did - leaving nothing to tell
 * a developer beyond that something did not happen.
 *
 * Three outcomes, not two: shared on purpose is its own answer. A sync or in-memory transport is meant
 * to stay shared, so saying so in a build log would put noise in front of the lines worth reading.
 */
final readonly class TransportPoolingVerdict
{
    /**
     * @param class-string|null $transportClass the concrete class to pool the transport as
     * @param string|null $leftSharedBecause why it cannot be pooled, when that is worth reporting
     */
    private function __construct(
        public ?string $transportClass = null,
        public ?string $leftSharedBecause = null,
    ) {}

    /**
     * @param class-string $transportClass
     */
    public static function pooledAs(string $transportClass): self
    {
        return new self(transportClass: $transportClass);
    }

    public static function leftShared(string $because): self
    {
        return new self(leftSharedBecause: $because);
    }

    /**
     * Left shared by design, with nothing to report about it.
     */
    public static function sharedOnPurpose(): self
    {
        return new self();
    }
}
