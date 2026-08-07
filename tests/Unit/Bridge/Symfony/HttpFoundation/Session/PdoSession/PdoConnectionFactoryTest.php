<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\HttpFoundation\Session\PdoSession;

use InvalidArgumentException;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession\PdoConnectionFactory;

final class PdoConnectionFactoryTest extends TestCase
{
    private string $tempDbFile;

    protected function tearDown(): void
    {
        if (!isset($this->tempDbFile) || !file_exists($this->tempDbFile)) {
            return;
        }

        unlink($this->tempDbFile);
    }

    public function testCreatesPdoFromSqliteMemoryUrl(): void
    {
        $pdo = PdoConnectionFactory::newInstanceFromDsnOrUrl('sqlite:///:memory:');

        $this->assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
        $this->assertCanCreateTableAndQuery($pdo);
    }

    public function testCreatesPdoFromSqliteAbsolutePathUrl(): void
    {
        $this->tempDbFile = sys_get_temp_dir() . '/swoole_bundle_sqlite_test_' . uniqid() . '.db';
        $url = 'sqlite:///' . $this->tempDbFile;

        $pdo = PdoConnectionFactory::newInstanceFromDsnOrUrl($url);

        $this->assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
        $this->assertFileExists($this->tempDbFile);
        $this->assertCanCreateTableAndQuery($pdo);
    }

    public function testCreatesPdoFromSqlite3AliasUrl(): void
    {
        $pdo = PdoConnectionFactory::newInstanceFromDsnOrUrl('sqlite3:///:memory:');

        $this->assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
        $this->assertCanCreateTableAndQuery($pdo);
    }

    public function testThrowsWhenSqlitePathMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "path" component is required for SQLite DSN');

        PdoConnectionFactory::newInstanceFromDsnOrUrl('sqlite://localhost');
    }

    public function testCreatesPdoFromMysqlUrl(): void
    {
        $pdo = PdoConnectionFactory::newInstanceFromDsnOrUrl('mysql://user:pass@' . $this->databaseHost() . ':3306/db');

        $this->assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
        $this->assertSame(1, $pdo->query('SELECT 1')->fetchColumn());
    }

    public function testCreatesPdoFromMysql2AliasUrl(): void
    {
        $pdo = PdoConnectionFactory::newInstanceFromDsnOrUrl(
            'mysql2://user:pass@' . $this->databaseHost() . ':3306/db',
        );

        $this->assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
        $this->assertSame(1, $pdo->query('SELECT 1')->fetchColumn());
    }

    public function testCreatesPdoFromMysqlUrlWithCharset(): void
    {
        $pdo = PdoConnectionFactory::newInstanceFromDsnOrUrl(
            'mysql://user:pass@' . $this->databaseHost() . ':3306/db?charset=utf8mb4',
        );

        $this->assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
        $this->assertSame(1, $pdo->query('SELECT 1')->fetchColumn());
    }

    public function testThrowsWhenUrlHasNoScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('URLs without scheme are not supported to configure the PdoSessionHandler');

        PdoConnectionFactory::newInstanceFromDsnOrUrl('no-scheme-at-all');
    }

    #[DataProvider('unsupportedSchemes')]
    public function testThrowsWhenSchemeIsUnsupported(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The scheme "unknownscheme" is not supported');

        PdoConnectionFactory::newInstanceFromDsnOrUrl($url);
    }

    /**
     * @return array<array{0: string}>
     */
    public static function unsupportedSchemes(): array
    {
        return [
            ['unknownscheme://user:pass@localhost/db'],
        ];
    }

    #[DataProvider('driverAliasesWithoutExtension')]
    public function testMapsSchemeToPdoDriver(string $url, string $driver): void
    {
        if (in_array($driver, PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped(sprintf('Extension pdo_%s is loaded.', $driver));
        }

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('could not find driver');

        PdoConnectionFactory::newInstanceFromDsnOrUrl($url);
    }

    /**
     * @return array<array{0: string, 1: string}>
     */
    public static function driverAliasesWithoutExtension(): array
    {
        return [
            ['postgres://user:pass@localhost:5432/dbname', 'pgsql'],
            ['postgresql://user:pass@localhost:5432/dbname', 'pgsql'],
            ['mssql://user:pass@localhost:1433/dbname', 'sqlsrv'],
        ];
    }

    private function assertCanCreateTableAndQuery(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, value TEXT)');
        $pdo->exec('INSERT INTO test (value) VALUES ("foo")');
        $value = $pdo->query('SELECT value FROM test WHERE id = 1')->fetchColumn();

        $this->assertSame('foo', $value);
    }

    private function databaseHost(): string
    {
        return getenv('DATABASE_HOST') ?: 'db';
    }
}
