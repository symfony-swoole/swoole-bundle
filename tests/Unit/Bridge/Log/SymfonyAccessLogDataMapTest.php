<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Log;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Log\SymfonyAccessLogDataMap;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;
use Symfony\Component\HttpFoundation\ServerBag;

final class SymfonyAccessLogDataMapTest extends TestCase
{
    /**
     * @var HttpFoundationRequest
     */
    private $request;

    private HttpFoundationResponse $response;

    protected function setUp(): void
    {
        $this->request = new HttpFoundationRequest();
        $this->response = new HttpFoundationResponse('My response', 200);
    }

    protected function tearDown(): void
    {
        HttpFoundationRequest::setTrustedProxies([], 0);
    }

    /**
     * @return iterable<array{array<string, string>, array<string, string>, string, array<string>}>
     */
    public static function provideServer(): iterable
    {
        yield 'no address' => [[], [], '-', []];
        yield 'x-forwarded-for' => [
            ['x-forwarded-for' => '3.3.3.3'],
            ['REMOTE_ADDR' => '4.4.4.4'],
            '3.3.3.3',
            ['4.4.4.4'],
        ];

        yield 'x-forwarded-for-multi' => [
            ['x-forwarded-for' => '1.1.1.1, 2.2.2.2'],
            ['REMOTE_ADDR' => '4.4.4.4'],
            '1.1.1.1',
            ['4.4.4.4', '2.2.2.2'],
        ];

        yield 'remote-addr' => [[], ['REMOTE_ADDR' => '4.4.4.4'], '4.4.4.4', []];
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $server
     * @param array<string> $trustedProxies
     */
    #[DataProvider('provideServer')]
    public function testClientIpIsProperlyResolved(
        array $headers,
        array $server,
        string $expectedIp,
        array $trustedProxies,
    ): void {
        if ($trustedProxies !== []) {
            HttpFoundationRequest::setTrustedProxies($trustedProxies, HttpFoundationRequest::HEADER_X_FORWARDED_FOR);
        }

        $this->request->server = new ServerBag($server);
        $this->request->headers = new HeaderBag($headers);
        $map = (new SymfonyAccessLogDataMap(false))->setRequestResponse($this->request, $this->response);

        $this->assertEquals($expectedIp, $map->getClientIp());
    }

    public function testGetRequestTimeAsRequestedByFormatterReturnsFormattedString(): void
    {
        $tz = new DateTimeZone(date_default_timezone_get());
        $date = new DateTimeImmutable('2021-12-02T02:21:12.4242', $tz);
        $this->request->server = new ServerBag(['REQUEST_TIME_FLOAT' => (float) $date->getTimestamp()]);
        $map = (new SymfonyAccessLogDataMap(false))->setRequestResponse($this->request, $this->response);
        $requestTime = $map->getRequestTime('begin:%d/%b/%Y:%H:%M:%S %z');

        $this->assertSame('[02/Dec/2021:02:21:12 ' . $date->format('O') . ']', $requestTime);
    }

    public function testGetHttpFoundationRequestTimeAsRequestedByFormatterReturnsFormattedString(): void
    {
        $tz = new DateTimeZone(date_default_timezone_get());
        $date = new DateTimeImmutable('2021-12-02T02:21:12.4242', $tz);
        $this->request->server = new ServerBag(['REQUEST_TIME_FLOAT' => (float) $date->getTimestamp()]);
        $map = (new SymfonyAccessLogDataMap(false))->setRequestResponse($this->request, $this->response);
        $requestTime = $map->getRequestTime('begin:%d/%b/%Y:%H:%M:%S %z');

        $this->assertSame('[02/Dec/2021:02:21:12 ' . $date->format('O') . ']', $requestTime);

        $date = new DateTimeImmutable('2021-12-03T02:22:12.4242', $tz);
        $this->request->server = new ServerBag(['REQUEST_TIME_FLOAT' => (float) $date->getTimestamp()]);
        $map = (new SymfonyAccessLogDataMap(false))->setRequestResponse($this->request, $this->response);
        $requestTime = $map->getRequestTime('begin:%d/%b/%Y:%H:%M:%S %z');

        $this->assertSame('[03/Dec/2021:02:22:12 ' . $date->format('O') . ']', $requestTime);
    }

    public function testGetHttpFoundationRequestTimeInDifferentTimeZoneAsUTC(): void
    {
        $oldTZ = date_default_timezone_get();
        date_default_timezone_set('Europe/Bratislava');
        $tz = new DateTimeZone(date_default_timezone_get());
        $date = new DateTimeImmutable('2021-12-02T02:21:12.4242', $tz);
        $this->request->server = new ServerBag(['REQUEST_TIME_FLOAT' => (float) $date->getTimestamp()]);
        $map = (new SymfonyAccessLogDataMap(false))->setRequestResponse($this->request, $this->response);
        $requestTime = $map->getRequestTime('begin:%d/%b/%Y:%H:%M:%S %z');

        $this->assertSame('[02/Dec/2021:02:21:12 ' . $date->format('O') . ']', $requestTime);
        date_default_timezone_set($oldTZ);
    }

    public function testGetServerNameReturnsHostname(): void
    {
        $map = new SymfonyAccessLogDataMap(false);
        $expectedHostname = gethostname() ?: '-';

        $this->assertSame($expectedHostname, $map->getServerName());
    }
}
