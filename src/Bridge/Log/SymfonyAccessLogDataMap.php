<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Log;

use DateTimeImmutable;
use IntlDateFormatter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SymfonyAccessLogDataMap implements AccessLogDataMap
{
    private const HOST_PORT_REGEX = '/^(?P<host>.*?)((?<!\]):(?P<port>\d+))?$/';

    /**
     * Timestamp when created, indicating end of request processing.
     */
    private float $endTime;

    /**
     * @param bool $useHostnameLookups whether or not to do a hostname lookup when retrieving the remote host name
     */
    public function __construct(
        private readonly Request $request,
        private readonly Response $response,
        private readonly bool $useHostnameLookups = false,
    ) {
        $this->endTime = microtime(true);
    }

    /**
     * Client IP address of the request (%a).
     */
    public function getClientIp(): string
    {
        $headers = ['x-real-ip', 'client-ip', 'x-forwarded-for'];

        foreach ($headers as $header) {
            if ($this->request->headers->has($header)) {
                return $this->request->headers->get($header);
            }
        }

        return $this->getServerParamIp('REMOTE_ADDR');
    }

    /**
     * Local IP-address (%A).
     */
    public function getLocalIp(): string
    {
        return $this->getServerParamIp('REMOTE_ADDR');
    }

    /**
     * Filename (%f).
     * Just dummy index file.
     */
    public function getFilename(): string
    {
        return getcwd() . '/public/index.php';
    }

    /**
     * Size of the message in bytes, excluding HTTP headers (%B, %b).
     */
    public function getResponseBodySize(string $default): string
    {
        $strlen = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';

        return (string) $strlen((string) $this->response->getContent()) ?: $default;
    }

    /**
     * Remote hostname (%h)
     * Will log the IP address if hostnameLookups is false.
     */
    public function getRemoteHostname(): string
    {
        $ip = $this->getClientIp();

        return $ip !== '-' && $this->useHostnameLookups
            ? (gethostbyaddr($ip) ?: '-')
            : $ip;
    }

    /**
     * The message protocol (%H).
     */
    public function getProtocol(): string
    {
        return $this->getServerParam('SERVER_PROTOCOL');
    }

    /**
     * The request method (%m).
     */
    public function getMethod(): string
    {
        return $this->getServerParam('REQUEST_METHOD');
    }

    /**
     * Returns a message header.
     */
    public function getRequestHeader(string $name): string
    {
        return $this->request->headers->get(strtolower($name), '-');
    }

    /**
     * Returns a message header.
     */
    public function getResponseHeader(string $name): string
    {
        return $this->response->headers->get(strtolower($name), '-');
    }

    /**
     * Returns a environment variable (%e).
     */
    public function getEnv(string $name): string
    {
        return getenv($name) ?: '-';
    }

    /**
     * Returns a cookie value (%{VARNAME}C).
     */
    public function getCookie(string $name): string
    {
        return (string) $this->request->cookies->get($name, '-');
    }

    /**
     * The canonical port of the server serving the request. (%p).
     */
    public function getPort(string $format): string
    {
        switch ($format) {
            case 'canonical':
            case 'local':
                preg_match(self::HOST_PORT_REGEX, $this->request->headers->get(strtolower('host'), ''), $matches);
                $port = $matches['port'] ?? null;
                $port = $port ?: $this->getServerParam('SERVER_PORT', '80');
                $scheme = $this->getServerParam('HTTPS', '');

                return $scheme && $port === '80' ? '443' : $port;
            default:
                return '-';
        }
    }

    /**
     * The query string (%q)
     * (prepended with a ? if a query string exists, otherwise an empty string).
     */
    public function getQuery(): string
    {
        $query = $this->request->getQueryString();

        return $query === null ? '' : sprintf('?%s', $query);
    }

    /**
     * Status. (%s).
     */
    public function getStatus(): string
    {
        return (string) $this->response->getStatusCode();
    }

    /**
     * Remote user if the request was authenticated. (%u).
     */
    public function getRemoteUser(): string
    {
        return $this->getServerParam('REMOTE_USER');
    }

    /**
     * The URL path requested, not including any query string. (%U).
     */
    public function getPath(): string
    {
        return $this->request->getPathInfo();
    }

    /**
     * The canonical ServerName of the server serving the request. (%v).
     */
    public function getHost(): string
    {
        return $this->getRequestHeader('host');
    }

    /**
     * The server name according to the UseCanonicalName setting. (%V).
     */
    public function getServerName(): string
    {
        return gethostname() ?: '-';
    }

    /**
     * First line of request. (%r).
     */
    public function getRequestLine(): string
    {
        return sprintf(
            '%s %s%s %s',
            $this->getMethod(),
            $this->getPath(),
            $this->getQuery(),
            $this->getProtocol()
        );
    }

    /**
     * Returns the response status line.
     */
    public function getResponseLine(): string
    {
        $reasonPhrase = Response::$statusTexts[$this->response->getStatusCode()];

        return sprintf(
            '%s %d %s',
            $this->getProtocol(),
            $this->getStatus(),
            $reasonPhrase
        );
    }

    /**
     * Bytes transferred (received and sent), including request and headers (%S).
     */
    public function getTransferredSize(): string
    {
        return (string) ((int) $this->getRequestMessageSize() + (int) $this->getResponseMessageSize()) ?: '-';
    }

    /**
     * Get the request message size (including first line and headers).
     */
    public function getRequestMessageSize(int|string $default = 0): int|string
    {
        $strlen = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';

        $bodySize = (int) $strlen((string) $this->request->getContent());

        if ($bodySize === 0) {
            return $default;
        }

        $firstLine = $strlen($this->getRequestLine());
        $headersSize = $this->getHeadersSize($this->request->headers->all());

        return $firstLine + 2 + $headersSize + 4 + $bodySize;
    }

    /**
     * Get the response message size (including first line and headers).
     */
    public function getResponseMessageSize(int|string $default = 0): int|string
    {
        $bodySize = (int) $this->getResponseBodySize('0');

        if ($bodySize === 0) {
            return $default;
        }

        $strlen = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
        $firstLineSize = (int) $strlen($this->getResponseLine());
        $headerSize = $this->getHeadersSize($this->response->headers->all());

        return $firstLineSize + 2 + $headerSize + 4 + $bodySize;
    }

    /**
     * Returns the request time (%t, %{format}t).
     */
    public function getRequestTime(string $format): string
    {
        $time = (int) $this->getServerParam('REQUEST_TIME_FLOAT');

        if (str_starts_with($format, 'begin:')) {
            $format = substr($format, 6);
        } elseif (str_starts_with($format, 'end:')) {
            $time = $this->endTime;
            $format = substr($format, 4);
        }

        switch ($format) {
            case 'sec':
                return sprintf('[%s]', round($time));
            case 'msec':
                return sprintf('[%s]', round($time * 1E3));
            case 'usec':
                return sprintf('[%s]', round($time * 1E6));
            default:
                // Cast to int first, as it may be a float
                $requestTime = new DateTimeImmutable('@' . (int) $time);

                return IntlDateFormatter::formatObject(
                    $requestTime,
                    '[' . StrftimeToICUFormatMap::mapStrftimeToICU($format, $requestTime) . ']'
                );
        }
    }

    /**
     * The time taken to serve the request. (%T, %{format}T).
     */
    public function getRequestDuration(string $format): string
    {
        /** @var float $begin */
        $begin = $this->getServerParam('REQUEST_TIME_FLOAT');

        return match ($format) {
            'us' => (string) round(($this->endTime - $begin) * 1E6),
            'ms' => (string) round(($this->endTime - $begin) * 1E3),
            default => (string) round($this->endTime - $begin),
        };
    }

    /**
     * Returns an server parameter value.
     */
    private function getServerParam(string $key, string $default = '-'): string
    {
        return (string) $this->request->server->get(strtoupper($key), $default);
    }

    /**
     * Returns an ip from the server params.
     */
    private function getServerParamIp(string $key): string
    {
        $ip = $this->getServerParam($key);

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) === false
            ? '-'
            : $ip;
    }

    /**
     * @param array<int, string|null>|array<string, array<int, string|null>> $headers
     */
    private function getHeadersSize(array $headers): int
    {
        $strlen = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';

        $allHeaders = [];
        foreach ($headers as $header => $value) {
            if (is_array($value)) {
                foreach ($value as $line) {
                    $allHeaders[] = sprintf('%s: %s', $header, $line);
                }

                continue;
            }
            $allHeaders[] = sprintf('%s: %s', $header, $value);
        }

        return (int) $strlen(implode("\r\n", $allHeaders));
    }
}
