<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Log;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Log\SymfonyAccessLogDataMap;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;
use Symfony\Component\HttpFoundation\ServerBag;

final class SymfonyAccessLogDataMapTest extends TestCase
{
    /**
     * @var HttpFoundationRequest|MockObject
     */
    private $request;

    private HttpFoundationResponse $response;

    protected function setUp(): void
    {
        $this->request = $this->createMock(HttpFoundationRequest::class);
        $this->response = new HttpFoundationResponse('My response', 200);
    }

    /**
     * @return iterable<array{array<string, string>, array<string, string>, string}>
     */
    public static function provideServer(): iterable
    {
        yield 'no address' => [[], [], '-'];
        yield 'x-real-ip' => [
            [
                'x-real-ip' => '1.1.1.1',
                'client-ip' => '2.2.2.2',
                'x-forwarded-for' => '3.3.3.3',
            ],
            [
                'REMOTE_ADDR' => '4.4.4.4',
            ],
            '1.1.1.1',
        ];

        yield 'client-ip' => [
            [
                'client-ip' => '2.2.2.2',
                'x-forwarded-for' => '3.3.3.3',
            ],
            [
                'REMOTE_ADDR' => '4.4.4.4',
            ],
            '2.2.2.2',
        ];

        yield 'x-forwarded-for' => [['x-forwarded-for' => '3.3.3.3'], ['REMOTE_ADDR' => '4.4.4.4'], '3.3.3.3'];
        yield 'remote-addr' => [[], ['REMOTE_ADDR' => '4.4.4.4'], '4.4.4.4'];
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $server
     */
    #[DataProvider('provideServer')]
    public function testClientIpIsProperlyResolved(array $headers, array $server, string $expectedIp): void
    {
        $this->request->server = new ServerBag($server);
        $this->request->headers = new HeaderBag($headers);
        $map = new SymfonyAccessLogDataMap($this->request, $this->response, false);

        $this->assertEquals($expectedIp, $map->getClientIp());
    }

    public function testGetRequestTimeAsRequestedByFormatterReturnsFormattedString(): void
    {
        $tz = new DateTimeZone(date_default_timezone_get());
        $date = new DateTimeImmutable('2021-12-02T02:21:12.4242', $tz);
        $this->request->server = new ServerBag(['REQUEST_TIME_FLOAT' => (float) $date->getTimestamp()]);
        $map = new SymfonyAccessLogDataMap($this->request, $this->response, false);
        $requestTime = $map->getRequestTime('begin:%d/%b/%Y:%H:%M:%S %z');

        $this->assertSame('[02/Dec/2021:02:21:12 ' . $date->format('O') . ']', $requestTime);
    }

    public function testGetHttpFoundationRequestTimeAsRequestedByFormatterReturnsFormattedString(): void
    {
        $tz = new DateTimeZone(date_default_timezone_get());
        $date = new DateTimeImmutable('2021-12-02T02:21:12.4242', $tz);
        $this->request->server = new ServerBag(['REQUEST_TIME_FLOAT' => (float) $date->getTimestamp()]);
        $map = new SymfonyAccessLogDataMap($this->request, $this->response, false);
        $requestTime = $map->getRequestTime('begin:%d/%b/%Y:%H:%M:%S %z');

        $this->assertSame('[02/Dec/2021:02:21:12 ' . $date->format('O') . ']', $requestTime);
    }
}
