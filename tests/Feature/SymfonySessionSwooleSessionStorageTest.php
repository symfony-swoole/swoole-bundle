<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Server\Session\SwooleTableStorage;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SymfonySessionSwooleSessionStorageTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testStorageIsConfiguredWithCustomParams(): void
    {
        self::bootKernel(['environment' => 'session_custom_params']);
        $storage = self::getContainer()->get(SwooleTableStorage::class);
        $this->assertInstanceOf(SwooleTableStorage::class, $storage);

        $reflection = new ReflectionClass($storage);

        $maxDataBytesProperty = $reflection->getProperty('maxSessionDataBytes');

        $this->assertSame(2048, $maxDataBytesProperty->getValue($storage));

        // OpenSwoole Table rounds max_active_sessions up to the next power of 2 internally.
        // The public $size property reflects the actual allocated row count.
        $sharedMemoryProperty = $reflection->getProperty('sharedMemory');
        $table = $sharedMemoryProperty->getValue($storage);

        // fixture configures max_active_sessions=500; OpenSwoole rounds up to 512 (2^9)
        $this->assertSame(512, $table->size);
    }

    #[DataProvider('environmentProvider')]
    public function testReturnTheSameDataForTheSameSessionId(string $env): void
    {
        $cookieLifetime = 5;
        $envs = [
            'APP_ENV' => $env,
            'COOKIE_LIFETIME' => $cookieLifetime,
        ];

        $clearCache = $this->createConsoleProcess(['cache:clear'], $envs);
        $clearCache->setTimeout(5);
        $clearCache->disableOutput();
        $clearCache->run();

        $this->assertProcessSucceeded($clearCache);

        $dropSchema = $this->createConsoleProcess(
            [
                'doctrine:schema:drop',
                '--full-database',
                '--force',
            ],
            $envs
        );
        $dropSchema->setTimeout(5);
        $dropSchema->disableOutput();
        $dropSchema->run();

        $this->assertProcessSucceeded($dropSchema);

        $migrations = $this->createConsoleProcess(
            [
                'doctrine:migrations:migrate',
                '--no-interaction',
            ],
            $envs
        );
        $migrations->setTimeout(5);
        $migrations->disableOutput();
        $migrations->run();

        $this->assertProcessSucceeded($migrations);

        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect(3, 1, true));

            $response1 = $client->send('/session/1')['response'];
            $this->assertSame(200, $response1['statusCode']);
            $this->assertArrayHasKey('set-cookie', $response1['headers']);
            $this->assertArrayHasKey('SWOOLESSID', $response1['cookies']);
            $sessionId1 = $response1['cookies']['SWOOLESSID'];
            $body1 = $response1['body'];

            $response2 = $client->send('/session/2')['response'];
            $this->assertArrayHasKey('SWOOLESSID', $response2['cookies']);
            $sessionId2 = $response2['cookies']['SWOOLESSID'];
            $body2 = $response2['body'];

            $this->assertSame($sessionId1, $sessionId2);
            $this->assertSame($body1, $body2);
        });
    }

    #[DataProvider('environmentProvider')]
    public function testDoNotReturnTheSameSessionForDifferentClients(string $env): void
    {
        $cookieLifetime = 5;
        $envs = [
            'APP_ENV' => $env,
            'COOKIE_LIFETIME' => $cookieLifetime,
        ];

        $clearCache = $this->createConsoleProcess(['cache:clear'], $envs);
        $clearCache->setTimeout(5);
        $clearCache->disableOutput();
        $clearCache->run();

        $this->assertProcessSucceeded($clearCache);

        $dropSchema = $this->createConsoleProcess(
            [
                'doctrine:schema:drop',
                '--full-database',
                '--force',
            ],
            $envs
        );
        $dropSchema->setTimeout(5);
        $dropSchema->disableOutput();
        $dropSchema->run();

        $this->assertProcessSucceeded($dropSchema);

        $migrations = $this->createConsoleProcess(
            [
                'doctrine:migrations:migrate',
                '--no-interaction',
            ],
            $envs
        );
        $migrations->setTimeout(5);
        $migrations->disableOutput();
        $migrations->run();

        $this->assertProcessSucceeded($migrations);

        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client1 = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client1->connect(3, 1, true));

            $response1 = $client1->send('/session/1')['response'];
            $this->assertArrayHasKey('SWOOLESSID', $response1['cookies']);
            $sessionId1 = $response1['cookies']['SWOOLESSID'];
            $body1 = $response1['body'];

            $client2 = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client2->connect());

            $response2 = $client2->send('/session/2')['response'];
            $this->assertArrayHasKey('SWOOLESSID', $response2['cookies']);
            $sessionId2 = $response2['cookies']['SWOOLESSID'];
            $body2 = $response2['body'];

            $this->assertNotSame($sessionId1, $sessionId2);
            $this->assertNotSame($body1, $body2);
        });
    }

    #[DataProvider('environmentProvider')]
    public function testExpireSession(string $env): void
    {
        $cookieLifetime = 1;
        $envs = [
            'APP_ENV' => $env,
            'COOKIE_LIFETIME' => $cookieLifetime,
        ];

        $clearCache = $this->createConsoleProcess(['cache:clear'], $envs);
        $clearCache->setTimeout(5);
        $clearCache->disableOutput();
        $clearCache->run();

        $this->assertProcessSucceeded($clearCache);

        $dropSchema = $this->createConsoleProcess(
            [
                'doctrine:schema:drop',
                '--full-database',
                '--force',
            ],
            $envs
        );
        $dropSchema->setTimeout(5);
        $dropSchema->disableOutput();
        $dropSchema->run();

        $this->assertProcessSucceeded($dropSchema);

        $migrations = $this->createConsoleProcess(
            [
                'doctrine:migrations:migrate',
                '--no-interaction',
            ],
            $envs
        );
        $migrations->setTimeout(5);
        $migrations->disableOutput();
        $migrations->run();

        $this->assertProcessSucceeded($migrations);

        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($cookieLifetime, $envs): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect(3, 1, true));

            $response1 = $client->send('/session/1')['response'];
            $this->assertSame(200, $response1['statusCode']);
            $this->assertArrayHasKey('SWOOLESSID', $response1['cookies']);

            $sessionId1 = $response1['cookies']['SWOOLESSID'];
            $setCookieHeader1 = $response1['headers']['set-cookie'];
            $body1 = $response1['body'];

            Coroutine::sleep($cookieLifetime + 3);

            $response2 = $client->send('/session/2')['response'];
            $this->assertSame(200, $response2['statusCode']);
            $this->assertArrayHasKey('SWOOLESSID', $response2['cookies']);

            $sessionId2 = $response2['cookies']['SWOOLESSID'];
            $setCookieHeader2 = $response2['headers']['set-cookie'];
            $body2 = $response2['body'];

            $this->assertNotSame($sessionId1, $sessionId2);
            $this->assertNotSame($setCookieHeader1, $setCookieHeader2);
            $this->assertNotSame($body1, $body2);
        });
    }

    public function testUpdateSession(): void
    {
        $cookieLifetime = 5;
        $envs = [
            'APP_ENV' => 'session',
            'COOKIE_LIFETIME' => $cookieLifetime,
        ];
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect(3, 1, true));

            $response1 = $client->send('/session/1')['response'];
            $this->assertSame(200, $response1['statusCode']);
            $this->assertArrayHasKey('SWOOLESSID', $response1['cookies']);
            $sessionId1 = $response1['cookies']['SWOOLESSID'];
            $body1 = $response1['body'];

            Coroutine::sleep(2);

            $response2 = $client->send('/session/2')['response'];
            $this->assertSame(200, $response2['statusCode']);
            $this->assertArrayHasKey('SWOOLESSID', $response2['cookies']);

            $sessionId2 = $response2['cookies']['SWOOLESSID'];
            $body2 = $response2['body'];

            $this->assertSame($sessionId1, $sessionId2);
            $this->assertSame($body1, $body2);
        });
    }

    public function testSessionDataExceedsMaxDataBytesIsRejected(): void
    {
        $cookieLifetime = 5;
        $envs = [
            'APP_ENV' => 'session',
            'COOKIE_LIFETIME' => $cookieLifetime,
        ];
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect(3, 1, true));

            // /session/large stores 600+ bytes which exceeds max_data_bytes=512 set in the session fixture
            $response = $client->send('/session/large')['response'];
            $this->assertSame(500, $response['statusCode']);
        });
    }

    #[DataProvider('environmentProvider')]
    public function testDoNotReturnTheSameSessionForDifferentClientsWithHttpCacheEnabled(string $env): void
    {
        $cookieLifetime = 5;
        $envs = [
            'APP_ENV' => $env . '_http_cache',
            'COOKIE_LIFETIME' => $cookieLifetime,
            // Only one worker to reliably verify app state is reset between requests.
            // Without it 2nd request may be handled by a different "clean" worker, which would distort test results.
            'WORKER_COUNT' => 1,
        ];

        $clearCache = $this->createConsoleProcess(['cache:clear'], $envs);
        $clearCache->setTimeout(5);
        $clearCache->disableOutput();
        $clearCache->run();

        $this->assertProcessSucceeded($clearCache);

        $dropSchema = $this->createConsoleProcess(
            [
                'doctrine:schema:drop',
                '--full-database',
                '--force',
            ],
            $envs
        );
        $dropSchema->setTimeout(5);
        $dropSchema->disableOutput();
        $dropSchema->run();

        $this->assertProcessSucceeded($dropSchema);

        $migrations = $this->createConsoleProcess(
            [
                'doctrine:migrations:migrate',
                '--no-interaction',
            ],
            $envs
        );
        $migrations->setTimeout(5);
        $migrations->disableOutput();
        $migrations->run();

        $this->assertProcessSucceeded($migrations);

        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client1 = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client1->connect(3, 1, true));

            $response1 = $client1->send('/session/1')['response'];
            $this->assertArrayHasKey('SWOOLESSID', $response1['cookies']);
            $sessionId1 = $response1['cookies']['SWOOLESSID'];
            $body1 = $response1['body'];

            $client2 = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client2->connect());

            $response2 = $client2->send('/session/2')['response'];
            $this->assertArrayHasKey('SWOOLESSID', $response2['cookies']);
            $sessionId2 = $response2['cookies']['SWOOLESSID'];
            $body2 = $response2['body'];

            $this->assertNotSame($sessionId1, $sessionId2);
            $this->assertNotSame($body1, $body2);
        });
    }

    /**
     * @param array{
     *    APP_ENV: string,
     *    APP_DEBUG: string,
     *    WORKER_COUNT: string,
     *    REACTOR_COUNT: string,
     *    OVERRIDE_PROD_ENV?: string,
     *  } $envs
     */
    #[DataProvider('coroutineTestDataProvider')]
    public function testReturnTheSameDataForTheSameSessionIdAndLuckyNumber(array $envs): void
    {
        $envs['COOKIE_LIFETIME'] = 1800;
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            // @todo investigate blocking lock on SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager.swoole_coop.wrapped
            $max = self::coverageEnabled() ? 8 : 40;
            $wg = $this->getSwoole()->waitGroup();
            $luckyNumbers = [];

            for ($i = 0; $i < $max; ++$i) {
                // phpcs:ignore SlevomatCodingStandard.PHP.DisallowReference.DisallowedInheritingVariableByReference
                go(function () use ($wg, &$luckyNumbers): void {
                    $wg->add();
                    $client = HttpClient::fromDomain('localhost', 9999, false);
                    $this->assertTrue($client->connect(3, 1, true));

                    $response1 = $client->send('/session/1')['response'];
                    $this->assertSame(200, $response1['statusCode']);
                    $this->assertArrayHasKey('set-cookie', $response1['headers']);
                    $this->assertArrayHasKey('SWOOLESSID', $response1['cookies']);
                    $sessionId1 = $response1['cookies']['SWOOLESSID'];
                    $body1 = $response1['body'];

                    $response2 = $client->send('/session/2')['response'];
                    $this->assertArrayHasKey('SWOOLESSID', $response2['cookies']);
                    $sessionId2 = $response2['cookies']['SWOOLESSID'];
                    $body2 = $response2['body'];

                    $this->assertSame($sessionId1, $sessionId2);
                    $this->assertSame($body1['luckyNumber'], $body2['luckyNumber']);
                    $luckyNumbers[$body1['luckyNumber']] = $body2['luckyNumber'];

                    $wg->done();
                });
            }

            $wg->wait($max);
            $this->assertCount($max, $luckyNumbers);
        });
    }

    /**
     * @return array<string, array<string>>
     */
    public static function environmentProvider(): array
    {
        return [
            'session' => ['session'],
            'session_pdo' => ['session_pdo'],
        ];
    }

    /**
     * @return array<array<array{
     *   APP_ENV: string,
     *   APP_DEBUG: string,
     *   WORKER_COUNT: string,
     *   REACTOR_COUNT: string,
     *   OVERRIDE_PROD_ENV?: string,
     * }>>
     */
    public static function coroutineTestDataProvider(): array
    {
        $configs = [];
        $envs = [
            'coroutines_session',
            'coroutines_session_pdo',
        ];

        foreach ($envs as $env) {
            // debug on
            $configs[$env . '_debug_on'] =
                [['APP_ENV' => $env, 'APP_DEBUG' => '1', 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1']];

            // debug off
            $configs[$env . '_debug_off'] =
                [['APP_ENV' => $env, 'APP_DEBUG' => '0', 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1']];

            // prod env with inline container factories and debug on
            $configs[$env . '_prod_env_debug_on_inline'] =
                [
                    [
                        'APP_ENV' => 'prod',
                        'APP_DEBUG' => '1',
                        'OVERRIDE_PROD_ENV' => $env,
                        'WORKER_COUNT' => '1',
                        'REACTOR_COUNT' => '1',
                    ],
                ];

            // prod env with inline container factories and debug off
            $configs[$env . '_prod_env_debug_off_inline'] =
                [
                    [
                        'APP_ENV' => 'prod',
                        'APP_DEBUG' => '0',
                        'OVERRIDE_PROD_ENV' => $env,
                        'WORKER_COUNT' => '1',
                        'REACTOR_COUNT' => '1',
                    ],
                ];
        }

        return $configs;
    }
}
