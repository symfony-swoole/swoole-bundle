<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Feature;

use Doctrine\ORM\EntityManager;
use K911\Swoole\Client\HttpClient;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Entity\Test;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;
use Swoole\Coroutine\WaitGroup;

final class SwooleServerCoroutinesTest extends ServerTestCase
{
    protected function setUp(): void
    {
        $this->markTestSkippedIfXdebugEnabled();
        $this->deleteVarDirectory();
    }

    /**
     * @dataProvider coroutineTestDataProvider
     */
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
            $this->assertTrue($initClient->connect());

            $start = microtime(true);
            $wg = new WaitGroup();

            for ($i = 0; $i < 10; ++$i) {
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

                    $wg->done();
                });
            }

            $wg->wait(10);
            $end = microtime(true);

            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect());
            $response = $client->send('/sleep')['response']; // request sleeps for 2 seconds
            $this->assertSame(200, $response['statusCode']);
            $this->assertStringContainsString('text/html', $response['headers']['content-type']);
            $this->assertStringContainsString('Check was true.', $response['body']);
            $this->assertStringContainsString('Checks: 10.', $response['body']);

            // without coroutines, it should be 40s, expected is 2-4s, 1.5s is slowness tolerance in initialization
            // 6.5 is tolerance for xdebug coverage
            self::assertLessThan(self::coverageEnabled() ? 6.5 : 5.5, $end - $start);
        });
    }

    /**
     * @dataProvider coroutineTestDataProvider
     */
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
            $this->assertTrue($initClient->connect());

            $start = microtime(true);
            $wg = new WaitGroup();

            for ($i = 0; $i < 40; ++$i) {
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

            self::assertLessThan(self::coverageEnabled() ? 6 : 0.5, $end - $start);

            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect());
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
     * @dataProvider coroutineTestDataProviderForTaskWorkers
     */
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
            $this->assertTrue($initClient->connect());

            $start = microtime(true);
            $requestData = [
                '&sleep=1000&append=1st',
                '&sleep=100&append=2nd',
                '&sleep=500&append=3rd',
            ];
            $wg = new WaitGroup();

            for ($i = 0; $i < 3; ++$i) {
                $query = '?fileName='.$fileName.$requestData[$i];
                go(function () use ($wg, $query): void {
                    $wg->add();
                    $client = HttpClient::fromDomain('localhost', 9999, false);
                    $this->assertTrue($client->connect());

                    $response = $client->send('/coroutines/message/sleep-and-append'.$query)['response'];
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
     * @dataProvider coroutineTestDataProviderForTaskWorkers
     */
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
            $this->assertTrue($initClient->connect());

            $start = microtime(true);
            $wg = new WaitGroup();

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
            usleep(1200000);
        });

        $container = static::getContainer();
        /** @var EntityManager $em */
        $em = $container->get('doctrine.orm.default_entity_manager');
        $repo = $em->getRepository(Test::class);
        $count = $repo->count([]);

        self::assertSame(3, $count);
    }

    public function coroutineTestDataProvider(): array
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

    public function coroutineTestDataProviderForTaskWorkers(): array
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

    private function generateNotExistingCustomTestFile(): string
    {
        return '/tmp/tfile-coroutines-'
            .$this->generateUniqueHash(4)
            .'-'
            .$this->currentUnixTimestamp()
            .'.txt';
    }
}
