<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Component\Concurrency;

/**
 * Sentinel pushed into a {@see SerialQueue} to wake a pinned consumer waiting on it, so that it checks right away
 * whether it may still keep what it holds instead of when its idle timeout next comes round.
 *
 * @internal
 */
final readonly class WakeMarker {}
