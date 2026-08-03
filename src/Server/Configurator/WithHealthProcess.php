<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Configurator;

use RuntimeException;
use Swoole\Http\Server;
use Swoole\Process;
use SwooleBundle\SwooleBundle\Server\Config\Socket;
use SwooleBundle\SwooleBundle\Server\Health\HealthReport;
use SwooleBundle\SwooleBundle\Server\Health\HealthReporter;

final readonly class WithHealthProcess implements Configurator
{
    public const string DEFAULT_PATH = '/healthz';

    private const int ACCEPT_TIMEOUT_SECONDS = 60;

    private const int READ_TIMEOUT_SECONDS = 2;

    private const int BIND_FAILURE_BACKOFF_SECONDS = 5;

    private const int MAX_REQUEST_HEADERS = 100;

    private const int MAX_LINE_BYTES = 8192;

    private const array REASON_PHRASES = [
        200 => '200 OK',
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
        $healthPath = $this->path;
        $reporter = $this->reporter;

        $server->addProcess(new Process(
            static function () use ($host, $port, $healthPath, $reporter): void {
                $listener = @stream_socket_server(sprintf('tcp://%s:%d', $host, $port), $errorNo, $errorMessage);

                if ($listener === false) {
                    sleep(self::BIND_FAILURE_BACKOFF_SECONDS);

                    throw new RuntimeException(
                        sprintf('Cannot bind health process to %s:%d: %s', $host, $port, $errorMessage),
                        $errorNo,
                    );
                }

                while (true) {
                    $connection = @stream_socket_accept($listener, self::ACCEPT_TIMEOUT_SECONDS);

                    if ($connection === false) {
                        usleep(10_000);

                        continue;
                    }

                    self::respond($connection, $healthPath, $reporter);
                }
            },
            false,
            SOCK_DGRAM,
            false,
        ));
    }

    /**
     * @param resource $connection
     */
    private static function respond($connection, string $healthPath, HealthReporter $reporter): void
    {
        stream_set_timeout($connection, self::READ_TIMEOUT_SECONDS);

        $requestLine = fgets($connection, self::MAX_LINE_BYTES);

        if (!is_string($requestLine)) {
            fclose($connection);

            return;
        }

        for ($header = 0; $header < self::MAX_REQUEST_HEADERS; ++$header) {
            $line = fgets($connection, self::MAX_LINE_BYTES);

            if ($line === false || trim($line) === '') {
                break;
            }
        }

        $path = explode('?', explode(' ', $requestLine)[1] ?? '')[0];

        if ($path === $healthPath) {
            $report = $reporter->report(time());
            $status = self::REASON_PHRASES[$report->statusCode];
            $body = $report->json();
        } else {
            $status = '404 Not Found';
            $body = (new HealthReport(404, ['ok' => false]))->json();
        }

        fwrite($connection, sprintf(
            "HTTP/1.1 %s\r\nContent-Type: application/json\r\nContent-Length: %d\r\nConnection: close\r\n\r\n%s",
            $status,
            strlen($body),
            $body,
        ));

        fclose($connection);
    }
}
