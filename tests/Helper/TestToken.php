<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Helper;

use Assert\Assertion;

/**
 * Identifies the parallel worker the current process belongs to.
 *
 * ParaTest exports TEST_TOKEN into every worker's environment, and that environment is inherited by the
 * console processes the feature tests spawn. Both halves of a feature test - the PHPUnit process and the
 * Swoole server it starts - therefore agree on which slice of the shared resources they own: the ports,
 * the fixture app's var directory, the pid file and the database.
 *
 * Outside ParaTest there is no token, and every resource keeps the name it had before parallelisation:
 * port 9999, "var", "var/swoole.pid" and the "db" database.
 */
final class TestToken
{
    /**
     * How many ports one worker owns. Feature tests address them by offset, see self::port().
     */
    public const int PORT_BLOCK_SIZE = 8;

    /**
     * The first port of the first worker's block - the port the feature tests used before parallelisation.
     */
    private const int PORT_BASE = 9999;

    private const string ENV_KEY = 'TEST_TOKEN';

    public static function isParallel(): bool
    {
        return self::rawToken() !== null;
    }

    /**
     * @return int<1, max>
     */
    public static function current(): int
    {
        $token = self::rawToken();

        if ($token === null || !ctype_digit($token)) {
            return 1;
        }

        return max(1, (int) $token);
    }

    /**
     * The n-th port of the current worker's block.
     *
     * @param int<0, max> $offset
     */
    public static function port(int $offset = 0): int
    {
        Assertion::between(
            $offset,
            0,
            self::PORT_BLOCK_SIZE - 1,
            sprintf('A test worker owns %d ports, offset %%s is outside its block.', self::PORT_BLOCK_SIZE)
        );

        return self::PORT_BASE + (self::current() - 1) * self::PORT_BLOCK_SIZE + $offset;
    }

    /**
     * Every port the current worker owns - what has to be free before and after each test.
     *
     * @return non-empty-list<int>
     */
    public static function ports(): array
    {
        return array_map(
            static fn(int $offset): int => self::port($offset),
            range(0, self::PORT_BLOCK_SIZE - 1)
        );
    }

    /**
     * Appended to resources that live under a fixed path, so workers do not share them. Empty outside
     * ParaTest, which keeps serial runs writing exactly where they always did.
     */
    public static function suffix(): string
    {
        return self::isParallel() ? '-' . self::current() : '';
    }

    /**
     * Each worker migrates, fills and drops a database of its own - the feature tests run
     * "doctrine:schema:drop --full-database", which would wipe a sibling's data mid-test.
     */
    public static function databaseName(): string
    {
        return self::isParallel() ? 'db_' . self::current() : 'db';
    }

    /**
     * Multiplies the timeouts feature tests put on their console processes. Starting a Swoole server
     * takes measurably longer when a dozen of them boot at once, and the timeouts were chosen against
     * an otherwise idle machine.
     */
    public static function timeoutFactor(): float
    {
        return self::isParallel() ? 3.0 : 1.0;
    }

    private static function rawToken(): ?string
    {
        $token = getenv(self::ENV_KEY);

        return $token === false || $token === '' ? null : $token;
    }
}
