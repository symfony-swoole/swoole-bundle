<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Doctrine;

use Doctrine\DBAL\Connection;
use Override;
use SwooleBundle\ResetterBundle\DBAL\Connection\DBALAliveKeeper;

/**
 * Records every ping that made it past the keeper under test.
 *
 * Without a decorated keeper it only records, which is all a test needs when the connection is a mere
 * identity for the keeper to tell apart. Given one - the real PingingDBALAliveKeeper - it also lets the
 * ping through, so the query is genuinely issued to the database.
 */
final class RecordingDBALAliveKeeper implements DBALAliveKeeper
{
    /**
     * @var list<array{connection: Connection, connectionName: string}>
     */
    private array $pings = [];

    public function __construct(private readonly ?DBALAliveKeeper $decorated = null) {}

    #[Override]
    public function keepAlive(Connection $connection, string $connectionName): void
    {
        $this->pings[] = ['connection' => $connection, 'connectionName' => $connectionName];

        $this->decorated?->keepAlive($connection, $connectionName);
    }

    /**
     * @return list<array{connection: Connection, connectionName: string}>
     */
    public function pings(): array
    {
        return $this->pings;
    }

    public function pingCount(): int
    {
        return count($this->pings);
    }

    /**
     * Drops the recorded pings, and with them the references to the connections they were made on -
     * needed by tests observing what happens once a connection is no longer referenced anywhere.
     */
    public function forgetRecordedPings(): void
    {
        $this->pings = [];
    }
}
