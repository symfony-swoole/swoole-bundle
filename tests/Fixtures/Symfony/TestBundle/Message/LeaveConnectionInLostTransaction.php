<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Message;

/**
 * Asks its handler to fail the way a deadlock inside a nested transaction does.
 *
 * @see \SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\MessageHandler\LeaveConnectionInLostTransactionHandler
 */
final readonly class LeaveConnectionInLostTransaction {}
