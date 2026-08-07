<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleServerHealthCheckFailureTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
        $this->killAllProcessesListeningOnPort(self::port(2));
    }

    public function testACheckThatCannotBeBuiltDoesNotRestartTheEvaluatorInALoop(): void
    {
        $envs = ['APP_ENV' => self::resolveEnvironment('health_checks_unconstructable')];
        $this->startServer($envs);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', self::port(2), false);
            $this->assertTrue($client->connect(self::connectTimeout(), 1, true));

            $before = $this->serverProcessIds();
            $this->assertNotEmpty($before, 'Could not find any server process.');

            Coroutine::sleep(5);

            $this->assertSame(
                $before,
                $this->serverProcessIds(),
                'A server process is being restarted in a loop.',
            );

            $response = $client->send('/healthz')['response'];

            $this->assertSame(503, $response['statusCode']);
            $this->assertTrue($response['body']['stale']);
        });
    }

    /**
     * @return list<int>
     */
    private function serverProcessIds(): array
    {
        $masterPid = (int) trim((string) file_get_contents($this->getVarDirectoryPath() . '/swoole.pid'));

        $childrenOf = [];

        foreach (explode("\n", trim((string) shell_exec('ps -o pid,ppid'))) as $line) {
            if (preg_match('/^\s*(\d+)\s+(\d+)/', $line, $matches) !== 1) {
                continue;
            }

            $childrenOf[(int) $matches[2]][] = (int) $matches[1];
        }

        $descendants = [];
        $queue = [$masterPid];

        while ($queue !== []) {
            foreach ($childrenOf[array_shift($queue)] ?? [] as $child) {
                $descendants[] = $child;
                $queue[] = $child;
            }
        }

        sort($descendants);

        return $descendants;
    }

    /**
     * @param array<string, string> $envs
     */
    private function startServer(array $envs): void
    {
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], $envs);

        $serverStart->setTimeout(5);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);
    }
}
