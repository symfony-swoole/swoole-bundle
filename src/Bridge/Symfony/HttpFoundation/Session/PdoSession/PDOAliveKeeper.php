<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession;

use PDO;

/**
 * Looks after a PDO connection that outlives its database's patience, as the resetter bundle's alive keepers look
 * after DBAL connections and Redis clusters: asked before the connection is used, it leaves behind one that answers.
 */
interface PDOAliveKeeper
{
    public function keepAlive(PDO $connection, string $connectionName): void;
}
