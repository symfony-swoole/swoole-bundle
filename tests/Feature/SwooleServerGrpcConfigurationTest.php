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

    /**
     * TODO: wrong. not needed test anymore
     */
    public function testGrpcNotImplementedResponse(): void
    {
        $serverStart = $this->createConsoleProcess(
            [
                'swoole:server:start',
                '--host=localhost',
                '--port=9999',
            ],
            [
                'APP_ENV' => 'grpc',
            ]
        );

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function (): void {
            $this->deferServerStop();

            // Port 9503 is designated for gRPC in our 'grpc' environment config
            $client = HttpClient::fromDomain('localhost', 9503, false);
            $this->assertTrue($client->connect());

            $response = $client->send('/')['response'];

            // Case 1 implementation returns 501 Not Implemented
            $this->assertEquals(501, $response['statusCode']);
            $this->assertEquals('application/json', $response['headers']['content-type']);
            $this->assertStringContainsString('gRPC server not implemented', $response['body']['error']);
        });
    }

    public function testApiAndGrpcCoexistence(): void
    {
        $serverStart = $this->createConsoleProcess(
            [
                'swoole:server:start',
                '--host=localhost',
                '--port=9999',
            ],
            [
                'APP_ENV' => 'api_grpc',
            ]
        );

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function (): void {
            $this->deferServerStop();

            // API Port (default 9200)
            $apiClient = HttpClient::fromDomain('localhost', 9200, false);
            $this->assertTrue($apiClient->connect());
            $apiResponse = $apiClient->send('/api/server')['response'];
            $this->assertEquals(200, $apiResponse['statusCode']);

            // gRPC Port (designated 9503)
            $grpcClient = HttpClient::fromDomain('localhost', 9503, false);
            $this->assertTrue($grpcClient->connect());
            $grpcResponse = $grpcClient->send('/')['response'];
            $this->assertEquals(501, $grpcResponse['statusCode']);
        });
    }
}
