<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use RuntimeException;
use Swoole\Http\Server;
use Swoole\Process;
use SwooleBundle\SwooleBundle\Server\Config\Socket;
use SwooleBundle\SwooleBundle\Server\Health\HealthReport;
use SwooleBundle\SwooleBundle\Server\Health\HealthReporter;

/**
 * @phpstan-type Pending = array<int, array{connection: resource, deadline: float, buffer: string}>
 */
final readonly class WithHealthProcess implements Configurator
{
    public const string DEFAULT_PATH = '/healthz';

    private const int BIND_FAILURE_BACKOFF_SECONDS = 5;

    private const float REQUEST_TIMEOUT_SECONDS = 2.0;

    private const float IDLE_WAIT_SECONDS = 60.0;

    private const int READ_CHUNK_BYTES = 8192;

    private const int MAX_REQUEST_BYTES = 16384;

    private const array REASON_PHRASES = [
        200 => '200 OK',
        404 => '404 Not Found',
        503 => '503 Service Unavailable',
    ];

    public function __construct(
        private Socket $socket,
        private HealthReporter $reporter,
        private string $path = self::DEFAULT_PATH,
    ) {}

    public function configure(Server $server): void
    {
        $host = $this->socket->host();
        $port = $this->socket->port();
        $path = $this->path;
        $reporter = $this->reporter;

        $server->addProcess(new Process(
            static function () use ($host, $port, $path, $reporter): void {
                $listener = @stream_socket_server(sprintf('tcp://%s:%d', $host, $port), $errorNo, $errorMessage);

                if ($listener === false) {
                    sleep(self::BIND_FAILURE_BACKOFF_SECONDS);

                    throw new RuntimeException(
                        sprintf('Cannot bind health process to %s:%d: %s', $host, $port, $errorMessage),
                        $errorNo,
                    );
                }

                $pending = [];

                while (true) {
                    $pending = self::serve($listener, $pending, $path, $reporter);
                }
            },
            false,
            SOCK_DGRAM,
            false,
        ));
    }

    /**
     * @param resource $listener
     * @param Pending $pending
     * @return Pending
     */
    private static function serve($listener, array $pending, string $path, HealthReporter $reporter): array
    {
        $now = microtime(true);
        $wait = self::IDLE_WAIT_SECONDS;
        $read = [$listener];

        foreach ($pending as $state) {
            $read[] = $state['connection'];
            $wait = min($wait, max(0.0, $state['deadline'] - $now));
        }

        $write = [];
        $except = [];
        $seconds = (int) $wait;
        $microseconds = (int) round(($wait - $seconds) * 1_000_000);

        if (@stream_select($read, $write, $except, $seconds, $microseconds) > 0) {
            foreach ($read as $readable) {
                $pending = $readable === $listener
                    ? self::accept($listener, $pending)
                    : self::advance($readable, $pending, $path, $reporter);
            }
        }

        return self::dropExpired($pending);
    }

    /**
     * @param resource $listener
     * @param Pending $pending
     * @return Pending
     */
    private static function accept($listener, array $pending): array
    {
        $connection = @stream_socket_accept($listener, 0);

        if ($connection === false) {
            return $pending;
        }

        stream_set_blocking($connection, false);

        $pending[get_resource_id($connection)] = [
            'connection' => $connection,
            'deadline' => microtime(true) + self::REQUEST_TIMEOUT_SECONDS,
            'buffer' => '',
        ];

        return $pending;
    }

    /**
     * @param resource $connection
     * @param Pending $pending
     * @return Pending
     */
    private static function advance($connection, array $pending, string $path, HealthReporter $reporter): array
    {
        $id = get_resource_id($connection);

        if (!isset($pending[$id])) {
            return $pending;
        }

        $chunk = fread($connection, self::READ_CHUNK_BYTES);

        if (!is_string($chunk) || $chunk === '') {
            return self::drop($id, $pending);
        }

        $buffer = $pending[$id]['buffer'] . $chunk;

        if (strlen($buffer) > self::MAX_REQUEST_BYTES) {
            return self::drop($id, $pending);
        }

        $pending[$id]['buffer'] = $buffer;

        if (!str_contains($buffer, "\r\n\r\n") && !str_contains($buffer, "\n\n")) {
            return $pending;
        }

        self::respond($connection, $buffer, $path, $reporter);

        return self::drop($id, $pending);
    }

    /**
     * @param resource $connection
     */
    private static function respond($connection, string $request, string $path, HealthReporter $reporter): void
    {
        $requestLine = substr($request, 0, strcspn($request, "\r\n"));
        $requestPath = explode('?', explode(' ', $requestLine)[1] ?? '')[0];

        $report = $requestPath === $path
            ? $reporter->report(time())
            : new HealthReport(404, ['ok' => false]);

        $body = $report->json();

        @fwrite($connection, sprintf(
            "HTTP/1.1 %s\r\nContent-Type: application/json\r\nContent-Length: %d\r\nConnection: close\r\n\r\n%s",
            self::REASON_PHRASES[$report->statusCode],
            strlen($body),
            $body,
        ));
    }

    /**
     * @param Pending $pending
     * @return Pending
     */
    private static function dropExpired(array $pending): array
    {
        $now = microtime(true);

        foreach ($pending as $id => $state) {
            if ($state['deadline'] > $now) {
                continue;
            }

            $pending = self::drop($id, $pending);
        }

        return $pending;
    }

    /**
     * @param Pending $pending
     * @return Pending
     */
    private static function drop(int $id, array $pending): array
    {
        $connection = $pending[$id]['connection'] ?? null;

        if (is_resource($connection)) {
            fclose($connection);
        }

        unset($pending[$id]);

        return $pending;
    }
}
