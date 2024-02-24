<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Co;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\DataProvider;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestAppKernel;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Entity\Test;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\NoAutowiring\ResetCountingRegistry;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;

final class SwooleServerCoroutinesTest extends ServerTestCase
{
    protected function setUp(): void
    {
        $this->markTestSkippedIfXdebugEnabled();
        $this->deleteVarDirectory();
    }

    /**
     * @param array{environment: string, debug: bool, override_prod_env?: string} $options
     */
    #[DataProvider('kernelEnvironmentDataProvider')]
    public function testOpcacheBlacklistFileGeneration(array $options): void
    {
        /** @var TestAppKernel $kernel */
        $kernel = self::createKernel($options);
        $application = new Application($kernel);
        $application->find('cache:clear'); // this will trigger cache generation
        $blacklistFile = $kernel->getCacheDir() . DIRECTORY_SEPARATOR .
            'swoole_bundle' . DIRECTORY_SEPARATOR .
            'opcache' . DIRECTORY_SEPARATOR . 'blacklist.txt';

        self::assertFileExists($blacklistFile);

        $content = file_get_contents($blacklistFile);

        self::assertIsString($content);

        $files = explode(PHP_EOL, trim($content));

        foreach ($files as $file) {
            self::assertFileExists($file);
        }
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
    public function testCoroutinesWithEnvs(array $envs): void
    {
        $clearCache = $this->createConsoleProcess(['cache:clear'], $envs);
        $clearCache->setTimeout(5);
        $clearCache->disableOutput();
        $clearCache->run();

        $this->assertProcessSucceeded($clearCache);

        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], $envs);

        $serverStart->setTimeout(5);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $initClient = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($initClient->connect(3, 1, true));

            $start = microtime(true);
            $wg = $this->getSwoole()->waitGroup();

            for ($i = 0; $i < 9; ++$i) {
                go(function () use ($wg): void {
                    $wg->add();
                    $client = HttpClient::fromDomain('localhost', 9999, false);
                    $this->assertTrue($client->connect());
                    $response = $client->send('/sleep')['response']; // request sleeps for 2 seconds
                    $this->assertSame(200, $response['statusCode']);
                    $this->assertStringContainsString('text/html', $response['headers']['content-type']);
                    // this also tests custom compile processor, which proxifies the sleeper service,
                    // so multiple instances will be created (one for each coroutine), so the counter in them
                    // is 1 for the first x concurrent requests
                    $this->assertMatchesRegularExpression('/Sleep was fine\. Count was [1-9]\./', $response['body']);
                    $this->assertStringContainsString('Service was proxified.', $response['body']);
                    $this->assertStringContainsString('Service2 was proxified.', $response['body']);
                    $this->assertStringContainsString('Service2 limit is 10.', $response['body']);
                    $this->assertStringContainsString('Always reset works.', $response['body']);
                    $this->assertStringContainsString('Safe always reseter is not a proxy.', $response['body']);
                    $this->assertStringContainsString('Safe Always reset works.', $response['body']);
                    $this->assertStringContainsString('TmpRepo was proxified.', $response['body']);
                    $this->assertStringContainsString('TmpRepo limit is 15.', $response['body']);
                    $this->assertStringContainsString('Connection limit is 12.', $response['body']);
                    $this->assertStringContainsString('Service pool for NonShared was added.', $response['body']);

                    $wg->done();
                });
            }

            $wg->wait(10);
            $end = microtime(true);

            // this has to be the 10th request becasue PCOV coverage tests run weirdly and don't free svc pool services
            // seems like global instances limit 20 is exhausted
            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect(3, 1, true));
            $response = $client->send('/sleep')['response']; // request sleeps for 2 seconds
            $this->assertSame(200, $response['statusCode']);
            $this->assertStringContainsString('text/html', $response['headers']['content-type']);
            $this->assertStringContainsString('Check was true.', $response['body']);
            $this->assertStringContainsString('Checks: 9.', $response['body']);

            // without coroutines, it should be 40s, expected is 2-4s, 1.5s is slowness tolerance in initialization
            // 6.5 is tolerance for xdebug coverage
            self::assertLessThan(self::coverageEnabled() ? 6.5 : 5.5, $end - $start);
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
    public function testCoroutinesAndDoctrineWithEnvs(array $envs): void
    {
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

        $serverStart = $this->createConsoleProcess(
            [
                'swoole:server:start',
                '--host=localhost',
                '--port=9999',
            ],
            $envs
        );

        $serverStart->setTimeout(5);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $initClient = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($initClient->connect(3, 1, true));

            $start = microtime(true);
            $wg = $this->getSwoole()->waitGroup();
            // PCOV is not compatible with coroutines, so CodeCoverageManager blocks service pools somehow when
            // service limit is 20
            // @todo investigate blocking lock on SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager.swoole_coop.wrapped
            $max = self::coverageEnabled() ? 8 : 40;

            for ($i = 0; $i < $max; ++$i) {
                go(function () use ($wg): void {
                    $wg->add();
                    $client = HttpClient::fromDomain('localhost', 9999, false);
                    $this->assertTrue($client->connect());
                    $response = $client->send('/doctrine')['response'];
                    $this->assertSame(200, $response['statusCode']);
                    $this->assertStringContainsString('text/html', $response['headers']['content-type']);
                    $wg->done();
                });
            }

            $wg->wait(10);
            $end = microtime(true);

            self::assertLessThan(self::coverageEnabled() ? 6 : 0.6, $end - $start);
            Co::sleep(1);

            // this has to be the 10th request becasue PCOV coverage tests run weirdly and don't free svc pool services
            // seems like global instances limit 20 is exhausted
            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect());
            $client->send('/doctrine'); // trigger em reset
            $response = $client->send('/doctrine-resets')['response']; // request sleeps for 2 seconds
            $this->assertSame(200, $response['statusCode']);
            $this->assertStringContainsString('application/json', $response['headers']['content-type']);

            $resets = $response['body'];
            self::assertCount(2, $resets);

            foreach ($resets as $reset) {
                // it cannot be determined how many connections are created
                self::assertGreaterThan(0, $reset);
            }
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
    public function testCoroutinesAndAdvancedDoctrineWithEnvs(array $envs): void
    {
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

        $serverStart = $this->createConsoleProcess(
            [
                'swoole:server:start',
                '--host=localhost',
                '--port=9999',
            ],
            $envs
        );

        $serverStart->setTimeout(6);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $initClient = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($initClient->connect(3, 1, true));

            $start = microtime(true);
            $wg = $this->getSwoole()->waitGroup();

            for ($i = 0; $i < 9; ++$i) {
                go(function () use ($wg): void {
                    $wg->add();
                    $client = HttpClient::fromDomain('localhost', 9999, false);
                    $this->assertTrue($client->connect());
                    $response = $client->send('/doctrine-advanced')['response'];
                    $this->assertSame(200, $response['statusCode']);
                    $this->assertStringContainsString('application/json', $response['headers']['content-type']);
                    $toAssert = [
                        'increment' => 10,
                        'resets' => 0,
                        'doctrineClass' => ResetCountingRegistry::class,
                    ];
                    $this->assertSame($toAssert, $response['body']);
                    $wg->done();
                });
            }

            $wg->wait(10);
            $end = microtime(true);

            self::assertLessThan(self::coverageEnabled() ? 8 : 0.8, $end - $start);
        });
    }

    /**
     * @param array{
     *    APP_ENV: string,
     *    APP_DEBUG: string,
     *    WORKER_COUNT: string,
     *    REACTOR_COUNT: string,
     *    TASK_WORKER_COUNT: string,
     *    OVERRIDE_PROD_ENV?: string,
     *  } $envs
     */
    #[DataProvider('coroutineTestDataProviderForTaskWorkers')]
    public function testCoroutinesAndTaskWorkersWithEnvs(array $envs): void
    {
        $clearCache = $this->createConsoleProcess(['cache:clear'], $envs);
        $clearCache->setTimeout(5);
        $clearCache->disableOutput();
        $clearCache->run();

        $this->assertProcessSucceeded($clearCache);

        $serverStart = $this->createConsoleProcess(
            [
                'swoole:server:start',
                '--host=localhost',
                '--port=9999',
            ],
            $envs
        );

        $serverStart->setTimeout(5);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $fileName = $this->generateNotExistingCustomTestFile();
        $this->runAsCoroutineAndWait(function () use ($fileName, $envs): void {
            $this->deferServerStop([], $envs);

            $initClient = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($initClient->connect(3, 1, true));

            $start = microtime(true);
            $requestData = [
                '&sleep=1000&append=1st',
                '&sleep=100&append=2nd',
                '&sleep=500&append=3rd',
            ];
            $wg = $this->getSwoole()->waitGroup();

            for ($i = 0; $i < 3; ++$i) {
                $query = '?fileName=' . $fileName . $requestData[$i];
                go(function () use ($wg, $query): void {
                    $wg->add();
                    $client = HttpClient::fromDomain('localhost', 9999, false);
                    $this->assertTrue($client->connect());

                    $response = $client->send('/coroutines/message/sleep-and-append' . $query)['response'];
                    $this->assertSame(200, $response['statusCode']);
                    $this->assertStringContainsString('text/plain', $response['headers']['content-type']);
                    $wg->done();
                });
            }

            $wg->wait(3);
            sleep(1);

            $end = microtime(true);
            // after one second, three rows should be in the file, not after 1.6s
            self::assertLessThan(self::coverageEnabled() ? 3 : 1.1, $end - $start);
        });

        $content = file_get_contents($fileName);

        self::assertNotEmpty($content);
        $lines = explode(PHP_EOL, trim($content));
        self::assertCount(3, $lines);
    }

    /**
     * @param array{
     *    APP_ENV: string,
     *    APP_DEBUG: string,
     *    WORKER_COUNT: string,
     *    REACTOR_COUNT: string,
     *    TASK_WORKER_COUNT: string,
     *    OVERRIDE_PROD_ENV?: string,
     *  } $envs
     */
    #[DataProvider('coroutineTestDataProviderForTaskWorkers')]
    public function testCoroutinesWithTaskWorkersWithDoctrine(array $envs): void
    {
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

        $serverStart = $this->createConsoleProcess(
            [
                'swoole:server:start',
                '--host=localhost',
                '--port=9999',
            ],
            $envs
        );

        $serverStart->setTimeout(5);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $initClient = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($initClient->connect(3, 1, true));

            $start = microtime(true);
            $wg = $this->getSwoole()->waitGroup();

            for ($i = 0; $i < 3; ++$i) {
                go(function () use ($wg): void {
                    $wg->add();
                    $client = HttpClient::fromDomain('localhost', 9999, false);
                    $this->assertTrue($client->connect());

                    $response = $client->send('/coroutines/message/run-dummy')['response'];
                    $this->assertSame(200, $response['statusCode']);
                    $this->assertStringContainsString('text/plain', $response['headers']['content-type']);
                    $wg->done();
                });
            }

            $wg->wait(3);
            $end = microtime(true);

            self::assertLessThan(self::coverageEnabled() ? 2 : 1.5, $end - $start);
            usleep(1_200_000);
        });

        $container = self::getContainer();
        /** @var EntityManager $em */
        $em = $container->get('doctrine.orm.default_entity_manager');
        $repo = $em->getRepository(Test::class);
        $count = $repo->count([]);

        self::assertSame(3, $count);
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
        return [
            // debug on
            [['APP_ENV' => 'coroutines', 'APP_DEBUG' => '1', 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1']],
            // debug off
            [['APP_ENV' => 'coroutines', 'APP_DEBUG' => '0', 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1']],
            // prod env with inline container factories and debug on
            [
                [
                    'APP_ENV' => 'prod',
                    'APP_DEBUG' => '1',
                    'OVERRIDE_PROD_ENV' => 'coroutines',
                    'WORKER_COUNT' => '1',
                    'REACTOR_COUNT' => '1',
                ],
            ],
            // prod env with inline container factories and debug off
            [
                [
                    'APP_ENV' => 'prod',
                    'APP_DEBUG' => '0',
                    'OVERRIDE_PROD_ENV' => 'coroutines',
                    'WORKER_COUNT' => '1',
                    'REACTOR_COUNT' => '1',
                ],
            ],
        ];
    }

    /**
     * @return array<array<array{
     *   APP_ENV: string,
     *   APP_DEBUG: string,
     *   WORKER_COUNT: string,
     *   REACTOR_COUNT: string,
     *   TASK_WORKER_COUNT: string,
     *   OVERRIDE_PROD_ENV?: string,
     * }>>
     */
    public static function coroutineTestDataProviderForTaskWorkers(): array
    {
        return [
            // debug on
            [
                [
                    'APP_ENV' => 'coroutines',
                    'APP_DEBUG' => '1',
                    'WORKER_COUNT' => '1',
                    'TASK_WORKER_COUNT' => '1',
                    'REACTOR_COUNT' => '1',
                ],
            ],
            // debug off
            [
                [
                    'APP_ENV' => 'coroutines',
                    'APP_DEBUG' => '0',
                    'WORKER_COUNT' => '1',
                    'TASK_WORKER_COUNT' => '1',
                    'REACTOR_COUNT' => '1',
                ],
            ],
            // prod env with inline container factories and debug on
            [
                [
                    'APP_ENV' => 'prod',
                    'APP_DEBUG' => '1',
                    'OVERRIDE_PROD_ENV' => 'coroutines',
                    'WORKER_COUNT' => '1',
                    'TASK_WORKER_COUNT' => '1',
                    'REACTOR_COUNT' => '1',
                ],
            ],
            // prod env with inline container factories and debug off
            [
                [
                    'APP_ENV' => 'prod',
                    'APP_DEBUG' => '0',
                    'OVERRIDE_PROD_ENV' => 'coroutines',
                    'WORKER_COUNT' => '1',
                    'TASK_WORKER_COUNT' => '1',
                    'REACTOR_COUNT' => '1',
                ],
            ],
        ];
    }

    /**
     * @return array<array<array{environment: string, debug: bool, override_prod_env?: string}>>
     */
    public static function kernelEnvironmentDataProvider(): array
    {
        return [
            [['environment' => 'prod', 'debug' => false, 'override_prod_env' => 'coroutines']],
            [['environment' => 'prod', 'debug' => true, 'override_prod_env' => 'coroutines']],
            [['environment' => 'coroutines', 'debug' => false]],
            [['environment' => 'coroutines', 'debug' => true]],
        ];
    }

    private function generateNotExistingCustomTestFile(): string
    {
        return '/tmp/tfile-coroutines-'
            . $this->generateUniqueHash(4)
            . '-'
            . $this->currentUnixTimestamp()
            . '.txt';
    }
}
