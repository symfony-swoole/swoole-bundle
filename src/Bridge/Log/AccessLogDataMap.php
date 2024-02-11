<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Log;

interface AccessLogDataMap
{
    /**
     * Client IP address of the request (%a).
     */
    public function getClientIp(): string;

    /**
     * Local IP-address (%A).
     */
    public function getLocalIp(): string;

    /**
     * Filename (%f).
     */
    public function getFilename(): string;

    /**
     * Size of the message in bytes, excluding HTTP headers (%B, %b).
     */
    public function getResponseBodySize(string $default): string;

    /**
     * Remote hostname (%h)
     * Will log the IP address if hostnameLookups is false.
     */
    public function getRemoteHostname(): string;

    /**
     * The message protocol (%H).
     */
    public function getProtocol(): string;

    /**
     * The request method (%m).
     */
    public function getMethod(): string;

    /**
     * Returns a message header.
     */
    public function getRequestHeader(string $name): string;

    /**
     * Returns a message header.
     */
    public function getResponseHeader(string $name): string;

    /**
     * Returns a environment variable (%e).
     */
    public function getEnv(string $name): string;

    /**
     * Returns a cookie value (%{VARNAME}C).
     */
    public function getCookie(string $name): string;

    /**
     * The canonical port of the server serving the request. (%p).
     */
    public function getPort(string $format): string;

    /**
     * The query string (%q)
     * (prepended with a ? if a query string exists, otherwise an empty string).
     */
    public function getQuery(): string;

    /**
     * Status. (%s).
     */
    public function getStatus(): string;

    /**
     * Remote user if the request was authenticated. (%u).
     */
    public function getRemoteUser(): string;

    /**
     * The URL path requested, not including any query string. (%U).
     */
    public function getPath(): string;

    /**
     * The canonical ServerName of the server serving the request. (%v).
     */
    public function getHost(): string;

    /**
     * The server name according to the UseCanonicalName setting. (%V).
     */
    public function getServerName(): string;

    /**
     * First line of request. (%r).
     */
    public function getRequestLine(): string;

    /**
     * Returns the response status line.
     */
    public function getResponseLine(): string;

    /**
     * Bytes transferred (received and sent), including request and headers (%S).
     */
    public function getTransferredSize(): string;

    /**
     * Get the request message size (including first line and headers).
     */
    public function getRequestMessageSize(int|string $default = 0): int|string;

    /**
     * Get the response message size (including first line and headers).
     */
    public function getResponseMessageSize(int|string $default = 0): int|string;

    /**
     * Returns the request time (%t, %{format}t).
     */
    public function getRequestTime(string $format): string;

    /**
     * The time taken to serve the request. (%T, %{format}T).
     */
    public function getRequestDuration(string $format): string;
}
