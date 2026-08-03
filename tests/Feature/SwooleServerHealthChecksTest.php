<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleServerHealthChecksTest extends ServerTestCase
{
    private const int APP_PORT = 9999;
    private const int HEALTH_PORT = 9997;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
        $this->killAllProcessesListeningOnPort(self::HEALTH_PORT);
        $this->clearUnhealthyFlag();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->clearUnhealthyFlag();

        parent::tearDown();
    }

    public function testRegisteredHealthChecksAreReportedAndCanFailTheEndpoint(): void
    {
        $envs = ['APP_ENV' => self::resolveEnvironment('health_checks')];
        $this->startServer($envs);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', self::HEALTH_PORT, false);
            $this->assertTrue($client->connect(3, 1, true));

            $healthy = $this->probeUntil($client, static fn(array $body): bool => $body['ok'] === true);

            $this->assertSame(200, $healthy['statusCode']);
            $this->assertSame(
                ['ok' => true, 'checks' => ['flagged' => ['ok' => true, 'detail' => '']]],
                $healthy['body'],
            );

            file_put_contents($this->unhealthyFlagPath(), 'flipped by test');

            $unhealthy = $this->probeUntil($client, static fn(array $body): bool => $body['ok'] === false);

            $this->assertSame(503, $unhealthy['statusCode']);
            $this->assertSame(
                ['ok' => false, 'checks' => ['flagged' => ['ok' => false, 'detail' => 'flipped by test']]],
                $unhealthy['body'],
            );
        });
    }

    public function testABlockingHealthCheckCannotWedgeTheEndpoint(): void
    {
        $envs = ['APP_ENV' => self::resolveEnvironment('health_checks_blocking')];
        $this->startServer($envs);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', self::HEALTH_PORT, false);
            $this->assertTrue($client->connect(3, 1, true));

            $startedAt = microtime(true);
            $response = $client->send('/healthz')['response'];
            $elapsed = microtime(true) - $startedAt;

            $this->assertLessThan(
                1.0,
                $elapsed,
                'A blocking check must not delay the endpoint.',
            );
            $this->assertSame(503, $response['statusCode']);
            $this->assertTrue($response['body']['stale']);

            Coroutine::sleep(5);

            $startedAt = microtime(true);
            $response = $client->send('/healthz')['response'];
            $elapsed = microtime(true) - $startedAt;

            $this->assertLessThan(1.0, $elapsed);
            $this->assertSame(503, $response['statusCode']);
            $this->assertTrue($response['body']['stale']);
        });
    }

    /**
     * @param callable(array<string, mixed>): bool $isSettled
     * @return array{statusCode: int, body: array<string, mixed>}
     */
    private function probeUntil(HttpClient $client, callable $isSettled, int $attempts = 10): array
    {
        $response = null;

        for ($attempt = 0; $attempt < $attempts; ++$attempt) {
            $response = $client->send('/healthz')['response'];

            if ($isSettled($response['body'])) {
                break;
            }

            Coroutine::sleep(1);
        }

        $this->assertNotNull($response, 'The health endpoint never answered.');

        return $response;
    }

    private function unhealthyFlagPath(): string
    {
        return $this->getVarDirectoryPath() . '/health-check-unhealthy';
    }

    private function clearUnhealthyFlag(): void
    {
        if (!file_exists($this->unhealthyFlagPath())) {
            return;
        }

        unlink($this->unhealthyFlagPath());
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
