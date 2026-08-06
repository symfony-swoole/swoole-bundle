<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Session;

use PHPUnit\Framework\TestCase;
use Swoole\Table;
use SwooleBundle\SwooleBundle\Server\Session\Exception\RuntimeException;
use SwooleBundle\SwooleBundle\Server\Session\SwooleTableStorage;

final class SwooleTableStorageTest extends TestCase
{
    /**
     * A table that has run out of room reports it rather than dropping the session.
     *
     * Left unreported it is close to invisible: the write succeeds as far as the caller can tell, the
     * next request finds nothing under that session id and is handed a fresh session, and what surfaces
     * much later is a user who was quietly signed out.
     *
     * Note how few rows it takes. The table below is declared with 64 and refuses long before that,
     * because the hash conflict pool is what actually runs out - so "the table is big enough for the
     * number of sessions" is not the guarantee it looks like.
     */
    public function testSetReportsATableThatCannotStoreTheSession(): void
    {
        if (extension_loaded('openswoole')) {
            self::markTestSkipped(
                'OpenSwoole raises an exception of its own from Table::set() instead of returning false, '
                . 'so there is nothing here for the guard to translate.'
            );
        }

        $storage = new SwooleTableStorage(new Table(64, 0.2), 64);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('the session table is full');

        for ($i = 0; $i < 5_000; ++$i) {
            // swoole raises a PHP warning of its own on the way to returning false. The exception is
            // what this asserts on, and letting the warning through only makes the suite report an
            // issue for a case it is deliberately provoking.
            @$storage->set(sprintf('session_%d', $i), 'payload', 60);
        }
    }

    public function testSetStoresASessionThatFits(): void
    {
        $storage = new SwooleTableStorage(new Table(64, 0.2), 64);

        $storage->set('a-session', 'payload', 60);

        self::assertSame('payload', $storage->get('a-session'));
        self::assertSame(1, $storage->count());
    }
}
