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
    }

    public function testCoroutinesWithDebugOn(): void
    {
        $clearCache = $this->createConsoleProcess([
            'cache:clear',
        ], ['APP_ENV' => 'coroutines', 'APP_DEBUG' => '1', 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1']);
        $clearCache->setTimeout(5);
        $clearCache->disableOutput();
        $clearCache->run();

        $this->assertProcessSucceeded($clearCache);

        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], ['APP_ENV' => 'coroutines', 'APP_DEBUG' => '1', 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1']);

        $serverStart->setTimeout(5);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function (): void {
            $this->deferServerStop();

            $initClient = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($initClient->connect());

            $start = microtime(true);
            $wg = new WaitGroup();
            $trueChecks = 0;

            for ($i = 0; $i < 4; ++$i) {
                go(function () use ($wg, &$trueChecks): void {
                    $wg->add();
                    $client = HttpClient::fromDomain('localhost', 9999, false);
                    $this->assertTrue($client->connect());
                    $response = $client->send('/sleep')['response']; // request sleeps for 2 seconds
                    $this->assertSame(200, $response['statusCode']);
                    $this->assertStringContainsString('text/html', $response['headers']['content-type']);
                    // this also tests custom compile processor, which proxifies the sleeper service,
                    // so multiple instances will be created (one for each coroutine), so the counter in them
                    // is 1 for the first 4 concurrent requests
                    $this->assertStringContainsString('Sleep was fine. Count was 1.', $response['body']);
                    $this->assertStringContainsString('Service was proxified.', $response['body']);

                    if (false !== strpos($response['body'], 'Check was true')) {
                        ++$trueChecks;
                    }

                    $wg->done();
                });
            }

            $wg->wait(10);
            $end = microtime(true);

            self::assertGreaterThanOrEqual(1, $trueChecks);
            // without coroutines, it should be 8, expected is 2, 1.5s is slowness tolerance in initialization
            // 4.5 is tolerance for xdebug coverage
            self::assertLessThan(self::coverageEnabled() ? 4.5 : 3.5, $end - $start);
        });
    }

    public function testCoroutinesWithDebugOff(): void
    {
        $clearCache = $this->createConsoleProcess([
            'cache:clear',
        ], ['APP_ENV' => 'coroutines', 'APP_DEBUG' => '0', 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1']);
        $clearCache->setTimeout(5);
        $clearCache->disableOutput();
        $clearCache->run();

        $this->assertProcessSucceeded($clearCache);

        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], ['APP_ENV' => 'coroutines', 'APP_DEBUG' => '0', 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1']);

        $serverStart->setTimeout(5);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function (): void {
            $this->deferServerStop();

            $initClient = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($initClient->connect());

            $start = microtime(true);
            $wg = new WaitGroup();
            $trueChecks = 0;

            for ($i = 0; $i < 4; ++$i) {
                go(function () use ($wg, &$trueChecks): void {
                    $wg->add();
                    $client = HttpClient::fromDomain('localhost', 9999, false);
                    $this->assertTrue($client->connect());
                    $response = $client->send('/sleep')['response']; // request sleeps for 2 seconds
                    $this->assertSame(200, $response['statusCode']);
                    $this->assertStringContainsString('text/html', $response['headers']['content-type']);
                    // this also tests custom compile processor, which proxifies the sleeper service,
                    // so multiple instances will be created (one for each coroutine), so the counter in them
                    // is 1 for the first 4 concurrent requests
                    $this->assertStringContainsString('Sleep was fine. Count was 1.', $response['body']);

                    if (false !== strpos($response['body'], 'Check was true')) {
                        ++$trueChecks;
                    }

                    $wg->done();
                });
            }

            $wg->wait(10);
            $end = microtime(true);

            self::assertGreaterThanOrEqual(1, $trueChecks);
            // without coroutines, it should be 8, expected is 2, 1.5s is slowness tolerance in initialization
            self::assertLessThan(3.5, $end - $start);
        });
    }

    public function testCoroutinesWithDoctrineAndWithDebugOff(): void
    {
        $clearCache = $this->createConsoleProcess([
            'cache:clear',
        ], ['APP_ENV' => 'coroutines', 'APP_DEBUG' => '0', 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1']);
        $clearCache->setTimeout(5);
        $clearCache->disableOutput();
        $clearCache->run();

        $this->assertProcessSucceeded($clearCache);

        $dropSchema = $this->createConsoleProcess([
            'doctrine:schema:drop',
            '--force',
        ], ['APP_ENV' => 'coroutines', 'APP_DEBUG' => '0', 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1']);
        $dropSchema->setTimeout(5);
        $dropSchema->disableOutput();
        $dropSchema->run();

        $this->assertProcessSucceeded($dropSchema);

        $createSchema = $this->createConsoleProcess([
            'doctrine:schema:create',
        ], ['APP_ENV' => 'coroutines', 'APP_DEBUG' => '0', 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1']);
        $createSchema->setTimeout(5);
        $createSchema->disableOutput();
        $createSchema->run();

        $this->assertProcessSucceeded($createSchema);

        $migrations = $this->createConsoleProcess([
            'doctrine:migrations:migrate',
            '--no-interaction',
        ], ['APP_ENV' => 'coroutines', 'APP_DEBUG' => '0', 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1']);
        $migrations->setTimeout(5);
        $migrations->disableOutput();
        $migrations->run();

        $this->assertProcessSucceeded($migrations);

        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], ['APP_ENV' => 'coroutines', 'APP_DEBUG' => '0', 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1']);

        $serverStart->setTimeout(5);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function (): void {
            $this->deferServerStop();

            $initClient = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($initClient->connect());

            $start = microtime(true);
            $wg = new WaitGroup();

            for ($i = 0; $i < 10; ++$i) {
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

            self::assertLessThan(self::coverageEnabled() ? 3 : 0.5, $end - $start);
        });
    }

    public function testCoroutinesWithTaskWorkers(): void
    {
        $clearCache = $this->createConsoleProcess([
            'cache:clear',
        ], [
            'APP_ENV' => 'coroutines',
            'APP_DEBUG' => '0',
            'WORKER_COUNT' => '1',
            'TASK_WORKER_COUNT' => '1',
            'REACTOR_COUNT' => '1',
        ]);
        $clearCache->setTimeout(5);
        $clearCache->disableOutput();
        $clearCache->run();

        $this->assertProcessSucceeded($clearCache);

        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], [
            'APP_ENV' => 'coroutines',
            'APP_DEBUG' => '0',
            'WORKER_COUNT' => '1',
            'TASK_WORKER_COUNT' => '1',
            'REACTOR_COUNT' => '1',
        ]);

        $serverStart->setTimeout(5);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $fileName = $this->generateNotExistingCustomTestFile();
        $this->runAsCoroutineAndWait(function () use ($fileName): void {
            $this->deferServerStop();

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
            $end = microtime(true);

            self::assertLessThan(1.5, $end - $start);
            usleep(1200000);
        });

        $content = file_get_contents($fileName);

        self::assertEquals(implode(PHP_EOL, ['2nd', '3rd', '1st']).PHP_EOL, $content);
    }

    public function testCoroutinesWithTaskWorkersWithDoctrine(): void
    {
        $clearCache = $this->createConsoleProcess([
            'cache:clear',
        ], [
            'APP_ENV' => 'coroutines',
            'APP_DEBUG' => '0',
            'WORKER_COUNT' => '1',
            'TASK_WORKER_COUNT' => '1',
            'REACTOR_COUNT' => '1',
        ]);
        $clearCache->setTimeout(5);
        $clearCache->disableOutput();
        $clearCache->run();

        $this->assertProcessSucceeded($clearCache);

        $dropSchema = $this->createConsoleProcess([
            'doctrine:schema:drop',
            '--force',
        ], [
            'APP_ENV' => 'coroutines',
            'APP_DEBUG' => '0',
            'WORKER_COUNT' => '1',
            'REACTOR_COUNT' => '1',
            'TASK_WORKER_COUNT' => '1',
        ]);
        $dropSchema->setTimeout(5);
        $dropSchema->disableOutput();
        $dropSchema->run();

        $this->assertProcessSucceeded($dropSchema);

        $createSchema = $this->createConsoleProcess([
            'doctrine:schema:create',
        ], [
            'APP_ENV' => 'coroutines',
            'APP_DEBUG' => '0',
            'WORKER_COUNT' => '1',
            'REACTOR_COUNT' => '1',
            'TASK_WORKER_COUNT' => '1',
        ]);
        $createSchema->setTimeout(5);
        $createSchema->disableOutput();
        $createSchema->run();

        $this->assertProcessSucceeded($createSchema);

        $migrations = $this->createConsoleProcess([
            'doctrine:migrations:migrate',
            '--no-interaction',
        ], [
            'APP_ENV' => 'coroutines',
            'APP_DEBUG' => '0',
            'WORKER_COUNT' => '1',
            'REACTOR_COUNT' => '1',
            'TASK_WORKER_COUNT' => '1',
        ]);
        $migrations->setTimeout(5);
        $migrations->disableOutput();
        $migrations->run();

        $this->assertProcessSucceeded($migrations);

        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], [
            'APP_ENV' => 'coroutines',
            'APP_DEBUG' => '0',
            'WORKER_COUNT' => '1',
            'TASK_WORKER_COUNT' => '1',
            'REACTOR_COUNT' => '1',
        ]);

        $serverStart->setTimeout(5);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function (): void {
            $this->deferServerStop();

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

            self::assertLessThan(1.5, $end - $start);
            usleep(1200000);
        });

        $container = static::getContainer();
        /** @var EntityManager $em */
        $em = $container->get('doctrine.orm.default_entity_manager');
        $repo = $em->getRepository(Test::class);
        $count = $repo->count([]);

        self::assertSame(3, $count);
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
