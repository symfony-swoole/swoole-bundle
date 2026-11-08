<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use SwooleBundle\SwooleBundle\Bridge\Doctrine\DBAL\ConnectionDisposer;
use UnexpectedValueException;

#[CoversClass(ConnectionDisposer::class)]
final class ConnectionDisposerTest extends TestCase
{
    public function testAConnectionItsPoolDropsIsClosed(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('close');

        (new ConnectionDisposer())->dispose($connection);
    }

    public function testAnythingButAConnectionIsRefused(): void
    {
        $this->expectException(UnexpectedValueException::class);

        (new ConnectionDisposer())->dispose(new stdClass());
    }

    public function testItDisposesOfDbalConnections(): void
    {
        self::assertSame(Connection::class, ConnectionDisposer::getSupportedClass());
    }
}
