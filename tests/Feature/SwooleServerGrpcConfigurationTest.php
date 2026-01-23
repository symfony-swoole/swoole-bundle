<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleServerGrpcConfigurationTest extends ServerTestCase
{
    protected function setUp(): void
    {
        $this->markTestSkippedIfXdebugEnabled();
        $this->deleteVarDirectory();
    }

    public function testApiAndGrpcServerCoexistence(): void
    {
        $serverStart = $this->createConsoleProcess(
            [
                'swoole:server:start',
                '--host=localhost',
                '--port=9999',
                '--api',
                '--grpc',
            ],
        );

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function (): void {
            $this->deferServerStop();

            $apiClient = HttpClient::fromDomain('localhost', 9200, false);
            $this->assertTrue($apiClient->connect());
            $apiResponse = $apiClient->send('/api/server')['response'];
            $this->assertEquals(200, $apiResponse['statusCode']);

            $grpcClient = HttpClient::fromDomain('localhost', 50051, false);
            $this->assertTrue($grpcClient->connect());
        });
    }
}
