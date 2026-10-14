<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\HttpFoundation\Session\PdoSession;

use Override;
use PDO;
use PDOException;
use PDOStatement;

/**
 * A connection whose socket the server reset: asked anything, it says so as pdo_mysql does - a notice from the
 * socket it could not write to, then the PDOException - until it is constructed again.
 */
final class ResetPDO extends PDO
{
    private int $constructed = 0;

    /**
     * @param array<int, mixed>|null $options
     */
    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        parent::__construct($dsn, $username, $password, $options);

        $this->constructed++;
    }

    #[Override]
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if ($this->isReset()) {
            trigger_error('PDO::query(): Send of 13 bytes failed with errno=32 Broken pipe', E_USER_NOTICE);

            throw new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
        }

        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    /**
     * Whether it is the connection that was reset still, not one constructed again since.
     */
    public function isReset(): bool
    {
        return $this->constructed === 1;
    }
}
