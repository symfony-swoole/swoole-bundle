<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use ArrayObject;
use Override;
use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleServerHealthProcessTest extends ServerTestCase
{
    private const int APP_PORT = 9999;
    private const int HEALTH_PORT = 9997;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
        $this->killAllProcessesListeningOnPort(self::HEALTH_PORT);
    }

    public function testHealthEndpointRespondsWhileEveryWorkerIsBlocked(): void
    {
        $envs = ['APP_ENV' => self::resolveEnvironment('health')];
        $this->startServer($envs);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $appClient = HttpClient::fromDomain('localhost', self::APP_PORT, false);
            $this->assertTrue($appClient->connect(3, 1, true));

            $blocking = new ArrayObject(['finished' => false]);
            $waitGroup = $this->getSwoole()->waitGroup();

            go(function () use ($waitGroup, $blocking): void {
                $waitGroup->add();
                $client = HttpClient::fromDomain('localhost', self::APP_PORT, false, ['timeout' => 15]);
                $response = $client->send('/test/blocking/5000', timeout: 15)['response'];

                $this->assertSame(200, $response['statusCode']);
                $blocking['finished'] = true;
                $waitGroup->done();
            });

            Coroutine::sleep(1);

            $startedAt = microtime(true);
            $response = $this->sendHealthRequest(self::HEALTH_PORT, '/healthz');
            $elapsed = microtime(true) - $startedAt;

            $this->assertFalse(
                $blocking['finished'],
                'The blocking request must still be occupying the worker while the health endpoint is probed.',
            );
            $this->assertSame(200, $response['statusCode']);
            $this->assertSame(['ok' => true], $response['body']);
            $this->assertLessThan(
                1.0,
                $elapsed,
                'Health endpoint must answer without queueing behind the blocked worker pool.',
            );

            $waitGroup->wait();
            $this->assertTrue($blocking['finished']);
        });
    }

    public function testHealthEndpointSurvivesAClientThatSendsNothing(): void
    {
        $envs = ['APP_ENV' => self::resolveEnvironment('health')];
        $this->startServer($envs);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $this->assertTrue($this->awaitHealthEndpoint(self::HEALTH_PORT));
            $this->assertSame(200, $this->sendHealthRequest(self::HEALTH_PORT, '/healthz')['statusCode']);

            $silent = stream_socket_client(sprintf('tcp://localhost:%d', self::HEALTH_PORT));
            $this->assertNotFalse($silent, 'Could not open the silent connection.');

            $startedAt = microtime(true);
            $response = $this->sendHealthRequest(self::HEALTH_PORT, '/healthz', 10);
            $elapsed = microtime(true) - $startedAt;

            $this->assertSame(200, $response['statusCode']);
            $this->assertSame(['ok' => true], $response['body']);
            $this->assertLessThan(
                1.0,
                $elapsed,
                'A client that sends nothing must not delay a probe while it waits to be timed out.',
            );

            fclose($silent);
        });
    }

    public function testHealthEndpointAnswersWhileSeveralClientsHoldConnectionsOpen(): void
    {
        $envs = ['APP_ENV' => self::resolveEnvironment('health')];
        $this->startServer($envs);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $this->assertTrue($this->awaitHealthEndpoint(self::HEALTH_PORT));

            $held = [];

            for ($connection = 0; $connection < 3; ++$connection) {
                $socket = stream_socket_client(sprintf('tcp://localhost:%d', self::HEALTH_PORT));
                $this->assertNotFalse($socket, 'Could not open a connection to hold open.');
                fwrite($socket, "GET /healthz HTTP/1.1\r\n");
                $held[] = $socket;
            }

            $startedAt = microtime(true);
            $response = $this->sendHealthRequest(self::HEALTH_PORT, '/healthz', 30);
            $elapsed = microtime(true) - $startedAt;

            $this->assertSame(200, $response['statusCode']);
            $this->assertSame(['ok' => true], $response['body']);
            $this->assertLessThan(
                1.0,
                $elapsed,
                'Unfinished requests must not queue up in front of a probe.',
            );

            foreach ($held as $socket) {
                fclose($socket);
            }
        });
    }

    public function testAnUnfinishedRequestIsDroppedAndCannotPostponeItsDeadline(): void
    {
        $envs = ['APP_ENV' => self::resolveEnvironment('health')];
        $this->startServer($envs);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $this->assertTrue($this->awaitHealthEndpoint(self::HEALTH_PORT));

            $drip = stream_socket_client(sprintf('tcp://localhost:%d', self::HEALTH_PORT));
            $this->assertNotFalse($drip, 'Could not open the dripping connection.');
            fwrite($drip, "GET /healthz HTTP/1.1\r\n");

            $startedAt = microtime(true);
            $received = '';
            $dropped = false;

            while (microtime(true) - $startedAt < 6.0) {
                @fwrite($drip, "X-Pad: keep-me-alive\r\n");

                $read = [$drip];
                $write = [];
                $except = [];

                if (stream_select($read, $write, $except, 0, 250_000) < 1) {
                    continue;
                }

                $chunk = fread($drip, 8_192);

                if ($chunk === false || $chunk === '') {
                    $dropped = true;

                    break;
                }

                $received .= $chunk;
            }

            $elapsed = microtime(true) - $startedAt;
            fclose($drip);

            $this->assertTrue($dropped, sprintf('The endpoint must drop the connection, got: %s', $received));
            $this->assertSame('', $received, 'An unfinished request must not be answered.');
            $this->assertLessThan(
                4.0,
                $elapsed,
                'The connection must be dropped near its deadline, no matter how long it keeps sending.',
            );
        });
    }

    public function testAnOversizedRequestIsDropped(): void
    {
        $envs = ['APP_ENV' => self::resolveEnvironment('health')];
        $this->startServer($envs);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $this->assertTrue($this->awaitHealthEndpoint(self::HEALTH_PORT));

            $oversized = stream_socket_client(sprintf('tcp://localhost:%d', self::HEALTH_PORT));
            $this->assertNotFalse($oversized, 'Could not open the oversized connection.');
            fwrite($oversized, "GET /healthz HTTP/1.1\r\n");
            fwrite($oversized, sprintf("X-Pad: %s\r\n", str_repeat('a', 64 * 1_024)));

            $read = [$oversized];
            $write = [];
            $except = [];
            $readable = stream_select($read, $write, $except, 1, 0);

            $this->assertSame(1, $readable, 'The endpoint must react to the oversized request.');

            $chunk = @fread($oversized, 8_192);

            $this->assertTrue(
                $chunk === false || $chunk === '',
                sprintf('An oversized request must not be buffered or answered, got: %s', var_export($chunk, true)),
            );

            fclose($oversized);

            $this->assertSame(
                200,
                $this->sendHealthRequest(self::HEALTH_PORT, '/healthz')['statusCode'],
                'Rejecting an oversized request must not take the endpoint down with it.',
            );
        });
    }

    public function testConcurrentProbesAreBothAnswered(): void
    {
        $envs = ['APP_ENV' => self::resolveEnvironment('health')];
        $this->startServer($envs);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $this->assertTrue($this->awaitHealthEndpoint(self::HEALTH_PORT));

            $first = stream_socket_client(sprintf('tcp://localhost:%d', self::HEALTH_PORT));
            $second = stream_socket_client(sprintf('tcp://localhost:%d', self::HEALTH_PORT));
            $this->assertNotFalse($first, 'Could not open the first connection.');
            $this->assertNotFalse($second, 'Could not open the second connection.');

            $request = "GET /healthz HTTP/1.1\r\nHost: localhost\r\n\r\n";
            fwrite($first, $request);
            fwrite($second, $request);

            $startedAt = microtime(true);
            $firstResponse = self::readResponse($first);
            $secondResponse = self::readResponse($second);
            $elapsed = microtime(true) - $startedAt;

            fclose($first);
            fclose($second);

            $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $firstResponse);
            $this->assertStringStartsWith("HTTP/1.1 200 OK\r\n", $secondResponse);
            $this->assertLessThan(1.0, $elapsed, 'Two probes arriving together must both be answered at once.');
        });
    }

    public function testProbesStayFastWhenConnectionsAreRepeatedlyOpenedAndAbandoned(): void
    {
        $envs = ['APP_ENV' => self::resolveEnvironment('health')];
        $this->startServer($envs);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $this->assertTrue($this->awaitHealthEndpoint(self::HEALTH_PORT));

            $startedAt = microtime(true);

            for ($round = 0; $round < 25; ++$round) {
                $abandoned = stream_socket_client(sprintf('tcp://localhost:%d', self::HEALTH_PORT));
                $this->assertNotFalse($abandoned, sprintf('Could not open connection %d.', $round));
                fclose($abandoned);

                $probe = stream_socket_client(sprintf('tcp://localhost:%d', self::HEALTH_PORT));
                $this->assertNotFalse($probe, sprintf('Could not open probe %d.', $round));
                fwrite($probe, "GET /healthz HTTP/1.1\r\nHost: localhost\r\n\r\n");

                $this->assertStringStartsWith(
                    "HTTP/1.1 200 OK\r\n",
                    self::readResponse($probe),
                    sprintf('Probe %d was not answered.', $round),
                );

                fclose($probe);
            }

            $this->assertLessThan(
                5.0,
                microtime(true) - $startedAt,
                'Connections left over from earlier rounds must not be slowing later probes down.',
            );
        });
    }

    public function testHealthEndpointIsServedOnTheConfiguredPath(): void
    {
        $envs = ['APP_ENV' => self::resolveEnvironment('health_custom_path')];
        $this->startServer($envs);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            // The health process binds its socket after the start command has returned.
            $this->assertTrue($this->awaitHealthEndpoint(self::HEALTH_PORT));

            $configured = $this->sendHealthRequest(self::HEALTH_PORT, '/alive');
            $this->assertSame(200, $configured['statusCode']);
            $this->assertSame(['ok' => true], $configured['body']);

            $default = $this->sendHealthRequest(self::HEALTH_PORT, '/healthz');
            $this->assertSame(404, $default['statusCode']);
        });
    }

    /**
     * @param resource $connection
     */
    private static function readResponse($connection, float $timeout = 5.0): string
    {
        $response = '';
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            $read = [$connection];
            $write = [];
            $except = [];

            if (stream_select($read, $write, $except, 0, 50_000) < 1) {
                continue;
            }

            $chunk = @fread($connection, 8_192);

            if (!is_string($chunk) || $chunk === '') {
                break;
            }

            $response .= $chunk;
        }

        return $response;
    }

    /**
     * @param array<string, string> $envs
     */
    private function startServer(array $envs): void
    {
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::APP_PORT),
        ], $envs);

        $serverStart->setTimeout(5);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);
    }
}
