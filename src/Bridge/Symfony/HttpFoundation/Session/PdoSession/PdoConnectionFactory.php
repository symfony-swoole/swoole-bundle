<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession;

use Assert\Assertion;
use InvalidArgumentException;
use PDO;
use SensitiveParameter;

final readonly class PdoConnectionFactory
{
    // phpcs:ignore SlevomatCodingStandard.Attributes.AttributeAndTargetSpacing
    public static function newInstanceFromDsnOrUrl(#[SensitiveParameter] string $dsnOrUrl): PDO
    {
        return new PDO(...self::constructorArgumentsFromDsnOrUrl($dsnOrUrl));
    }

    /**
     * What the connection of a DSN or URL is constructed with: a connection the server has closed is made again
     * with the same (PingingPDOAliveKeeper).
     *
     * @return array{0: string, 1: string|null, 2: string|null, 3: array<int, mixed>}
     */
    // phpcs:ignore SlevomatCodingStandard.Attributes.AttributeAndTargetSpacing
    public static function constructorArgumentsFromDsnOrUrl(#[SensitiveParameter] string $dsnOrUrl): array
    {
        // (pdo_)?sqlite3?:///... => (pdo_)?sqlite3?://localhost/... or else the URL will be invalid
        $url = preg_replace('#^((?:pdo_)?sqlite3?):///#', '$1://localhost/', $dsnOrUrl);

        Assertion::string($url, 'The provided DSN or URL must be a string, %s given.');

        $params = parse_url($url);

        if ($params === false) {
            // If the URL is not valid, let's assume it might be a DSN already.
            return self::constructorArguments($dsnOrUrl);
        }

        $username = null;
        $password = null;
        $params = array_map('rawurldecode', $params); // @phpstan-ignore-line

        // Override the default username and password.
        // Values passed through options will still win over these in the constructor.
        if (isset($params['user'])) {
            $username = $params['user'];
        }

        if (isset($params['pass'])) {
            $password = $params['pass'];
        }

        if (!isset($params['scheme'])) {
            throw new InvalidArgumentException(
                'URLs without scheme are not supported to configure the PdoSessionHandler.',
            );
        }

        $driverAliasMap = [
            'mssql' => 'sqlsrv',
            'mysql2' => 'mysql', // Amazon RDS, for some weird reason
            'postgres' => 'pgsql',
            'postgresql' => 'pgsql',
            'sqlite3' => 'sqlite',
        ];

        $driver = $driverAliasMap[$params['scheme']] ?? $params['scheme'];

        // Doctrine DBAL supports passing its internal pdo_* driver names directly too
        // (allowing both dashes and underscores). This allows supporting the same here.
        if (str_starts_with($driver, 'pdo_') || str_starts_with($driver, 'pdo-')) {
            $driver = substr($driver, 4);
        }

        $dsn = null;
        switch ($driver) {
            case 'mysql':
                $dsn = 'mysql:';
                if (($params['query'] ?? '') !== '') {
                    /**
                     * @var array{charset?: string, unix_socket?: string} $queryParams
                     */
                    $queryParams = [];
                    parse_str($params['query'], $queryParams);

                    if (($queryParams['charset'] ?? '') !== '') {
                        $dsn .= 'charset=' . $queryParams['charset'] . ';'; // @phpstan-ignore-line
                    }

                    if (($queryParams['unix_socket'] ?? '') !== '') {
                        $dsn .= 'unix_socket=' . $queryParams['unix_socket'] . ';'; // @phpstan-ignore-line

                        if (isset($params['path'])) {
                            $dbName = substr($params['path'], 1); // Remove the leading slash
                            $dsn .= 'dbname=' . $dbName . ';';
                        }

                        return self::constructorArguments($dsn, $username, $password);
                    }
                }
                // If "unix_socket" is not in the query, we continue with the same process as pgsql
                // no break
            case 'pgsql':
                $dsn ??= 'pgsql:';

                if (isset($params['host']) && $params['host'] !== '') {
                    $dsn .= 'host=' . $params['host'] . ';';
                }

                if (isset($params['port']) && $params['port'] !== '') {
                    $dsn .= 'port=' . $params['port'] . ';';
                }

                if (isset($params['path'])) {
                    $dbName = substr($params['path'], 1); // Remove the leading slash
                    $dsn .= 'dbname=' . $dbName . ';';
                }

                return self::constructorArguments($dsn, $username, $password);
            case 'sqlite':
                Assertion::keyExists($params, 'path', 'The "path" component is required for SQLite DSN.');

                return self::constructorArguments('sqlite:' . substr($params['path'], 1), $username, $password);
            case 'sqlsrv':
                $dsn = 'sqlsrv:server=';

                if (isset($params['host'])) {
                    $dsn .= $params['host'];
                }

                if (isset($params['port']) && $params['port'] !== '') {
                    $dsn .= ',' . $params['port'];
                }

                if (isset($params['path'])) {
                    $dbName = substr($params['path'], 1); // Remove the leading slash
                    $dsn .= ';Database=' . $dbName;
                }

                return self::constructorArguments($dsn, $username, $password);
            default:
                throw new InvalidArgumentException(
                    sprintf(
                        'The scheme "%s" is not supported by the PdoSessionHandler URL configuration. '
                        . 'Pass a PDO DSN directly.',
                        $params['scheme']
                    )
                );
        }
    }

    /**
     * @return array{0: string, 1: string|null, 2: string|null, 3: array<int, mixed>}
     */
    private static function constructorArguments(
        string $dsn,
        ?string $username = null,
        #[SensitiveParameter]
        ?string $password = null,
    ): array {
        return [$dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]];
    }
}
