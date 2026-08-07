<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Helper;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Gives every parallel worker a database of its own.
 *
 * Several feature tests reset their fixtures with "doctrine:schema:drop --full-database" and then replay
 * the migrations, and one of them counts the rows of the whole session table. None of that survives a
 * sibling worker working in the same schema, so each worker gets "db_<token>" instead of the shared "db".
 *
 * The fixture user creates its own database here, which it may do because it was granted the whole family
 * up front - in a GRANT an escaped underscore is a literal one, so `db\_%` covers every worker database
 * including the ones that do not exist yet. docker/mysql/init installs that grant when MySQL first
 * initialises and CI installs it after starting its container, which is the only place the privilege to
 * hand it out naturally lives: issuing the grant needs root, so nothing here could do it for itself.
 */
final class TestDatabase
{
    /**
     * The account the fixture app connects with - see app/config/doctrine.php.
     */
    private const string FIXTURE_USER = 'user';
    private const string FIXTURE_PASSWORD = 'pass';

    private static bool $ensured = false;

    /**
     * Creates the current worker's database if it does not exist yet. A no-op outside ParaTest: the shared
     * "db" database is part of the fixture stack already.
     */
    public static function ensureExists(): void
    {
        if (self::$ensured || !TestToken::isParallel()) {
            return;
        }

        self::$ensured = true;

        $database = TestToken::databaseName();

        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d', self::env('DATABASE_HOST', 'db'), 3306),
                self::FIXTURE_USER,
                self::FIXTURE_PASSWORD,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );

            // Identifiers cannot be bound, and MySQL has no quoting helper for them - so rather than
            // escaping the name, refuse anything that is not one we could have built ourselves.
            self::assertPlainIdentifier($database);

            $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s`', $database));
        } catch (PDOException $exception) {
            throw new RuntimeException(
                sprintf(
                    'Could not create the "%s" database for test worker %d. Parallel feature tests give '
                    . 'every worker a database of its own, which "%s" may only create once it has been '
                    . 'granted them: GRANT ALL ON `db\_%%`.* TO \'%s\'@\'%%\'; - docker/mysql/init installs '
                    . 'it on a fresh MySQL, an existing data directory needs it applying by hand.',
                    $database,
                    TestToken::current(),
                    self::FIXTURE_USER,
                    self::FIXTURE_USER,
                ),
                0,
                $exception,
            );
        }
    }

    private static function assertPlainIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1) {
            return;
        }

        throw new RuntimeException(sprintf('Refusing to use "%s" as a MySQL identifier.', $identifier));
    }

    private static function env(string $name, string $default): string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? $default : $value;
    }
}
