<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use SwooleBundle\SwooleBundle\Bridge\Doctrine\DBAL\ConnectionStabilityChecker;
use UnexpectedValueException;

#[CoversClass(ConnectionStabilityChecker::class)]
final class ConnectionStabilityCheckerTest extends TestCase
{
    public function testAConnectionOutsideATransactionIsStable(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::never())->method('close');

        self::assertTrue((new ConnectionStabilityChecker())->isStable($connection));
    }

    /**
     * What a deadlock inside a nested transaction leaves behind: a connection that believes it is two levels
     * into a transaction the server has already rolled back. It is closed, and said so, before it is dropped.
     */
    public function testAConnectionLeftInsideATransactionIsClosedAndUnstable(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->method('getTransactionNestingLevel')->willReturn(2);
        $connection->expects(self::once())->method('close');
        $errorLog = tempnam(sys_get_temp_dir(), 'error_log');
        $previous = ini_set('error_log', (string) $errorLog);

        try {
            self::assertFalse((new ConnectionStabilityChecker())->isStable($connection));
            self::assertStringContainsString(
                'still inside a transaction (nesting level 2)',
                (string) file_get_contents((string) $errorLog),
            );
        } finally {
            ini_set('error_log', (string) $previous);
            unlink((string) $errorLog);
        }
    }

    public function testAnythingButAConnectionIsRefused(): void
    {
        $this->expectException(UnexpectedValueException::class);

        (new ConnectionStabilityChecker())->isStable(new stdClass());
    }

    public function testItChecksDbalConnections(): void
    {
        self::assertSame(Connection::class, ConnectionStabilityChecker::getSupportedClass());
    }
}
