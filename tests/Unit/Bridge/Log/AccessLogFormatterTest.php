<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Unit\Bridge\Log;

use K911\Swoole\Bridge\Log\AccessLogDataMap;
use K911\Swoole\Bridge\Log\AccessLogFormatter;
use PHPUnit\Framework\TestCase;

class AccessLogFormatterTest extends TestCase
{
    public function testFormatterDelegatesToDataMapToReplacePlaceholdersInFormat(): void
    {
        $hostname = gethostname();

        $dataMap = $this->createMock(AccessLogDataMap::class);
        $dataMap->method('getClientIp')->willReturn('127.0.0.10'); // %a
        $dataMap->method('getLocalIp')->willReturn('127.0.0.1'); // %A
        $dataMap
            ->expects($this->exactly(2))
            ->method('getResponseBodySize')
            ->willReturnCallback(
                fn (string $default) => match ([$default]) { // @phpstan-ignore-line
                    ['0'] => '1234', // %B
                    ['-'] => '1234', // %b
                }
            )
        ;
        $dataMap
            ->expects($this->exactly(3))
            ->method('getRequestDuration')
            ->willReturnCallback(
                fn (string $format) => match ([$format]) { // @phpstan-ignore-line
                    ['ms'] => '4321', // %D
                    ['s'] => '22', // %T
                    ['us'] => '22', // %{us}T
                }
            )
        ;
        $dataMap->method('getFilename')->willReturn(__FILE__); // %f
        $dataMap->method('getRemoteHostname')->willReturn($hostname); // %h
        $dataMap->method('getProtocol')->willReturn('HTTP/1.1'); // %H
        $dataMap->method('getMethod')->willReturn('POST'); // %m
        $dataMap
            ->expects($this->exactly(2))
            ->method('getPort')
            ->willReturnCallback(
                fn (string $format) => match ([$format]) { // @phpstan-ignore-line
                    ['canonical'] => '9000', // %p
                    ['local'] => '9999', // %{local}p
                }
            )
        ;
        $dataMap->method('getQuery')->willReturn('?foo=bar'); // %q
        $dataMap->method('getRequestLine')->willReturn('POST /path?foo=bar HTTP/1.1'); // %r
        $dataMap->method('getStatus')->willReturn('202'); // %s
        $dataMap
            ->expects($this->exactly(2))
            ->method('getRequestTime')
            ->willReturnCallback(
                fn (string $format) => match ([$format]) { // @phpstan-ignore-line
                    ['begin:%d/%b/%Y:%H:%M:%S %z'] => '[1234567890]', // %t
                    ['end:sec'] => '[1234567890]', // %{end:sec}t
                }
            )
        ;

        $dataMap->method('getRemoteUser')->willReturn('swoole'); // %u
        $dataMap->method('getPath')->willReturn('/path'); // %U
        $dataMap->method('getHost')->willReturn('swoole.local'); // %v
        $dataMap->method('getServerName')->willReturn('swoole.local'); // %V
        $dataMap->method('getRequestMessageSize')->with('-')->willReturn(78); // %I
        $dataMap->method('getResponseMessageSize')->with('-')->willReturn(89); // %O
        $dataMap->method('getTransferredSize')->willReturn('123'); // %S
        $dataMap->method('getCookie')->with('cookie_name')->willReturn('chocolate'); // %{cookie_name}C
        $dataMap->method('getEnv')->with('env_name')->willReturn('php'); // %{env_name}e
        $dataMap->method('getRequestHeader')->with('X-Request-Header')->willReturn('request'); // %{X-Request-Header}i
        $dataMap->method('getResponseHeader')->with('X-Response-Header')->willReturn('response'); // %{X-Response-Header}o

        $format = '%a %A %B %b %D %f %h %H %m %p %q %r %s %t %T %u %U %v %V %I %O %S'
            .' %{cookie_name}C %{env_name}e %{X-Request-Header}i %{X-Response-Header}o'
            .' %{local}p %{end:sec}t %{us}T';
        $expected = [
            '127.0.0.10',
            '127.0.0.1',
            '1234',
            '1234',
            '4321',
            __FILE__,
            $hostname,
            'HTTP/1.1',
            'POST',
            '9000',
            '?foo=bar',
            'POST /path?foo=bar HTTP/1.1',
            '202',
            '[1234567890]',
            '22',
            'swoole',
            '/path',
            'swoole.local',
            'swoole.local',
            '78',
            '89',
            '123',
            'chocolate',
            'php',
            'request',
            'response',
            '9999',
            '[1234567890]',
            '22',
        ];
        $expected = implode(' ', $expected);

        $formatter = new AccessLogFormatter($format);

        $message = $formatter->format($dataMap);

        $this->assertEquals($expected, $message);
    }
}
