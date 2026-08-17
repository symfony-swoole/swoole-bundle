<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Client\Http;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SymfonyMessengerSwooleTaskTransportTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testStartServerDispatchMessage(): void
    {
        $testFile = $this->generateNotExistingCustomTestFile();
        $testFilePath = self::FIXTURE_RESOURCES_DIR . DIRECTORY_SEPARATOR . $testFile;
        $testFileContent = $this->generateUniqueHash(16);

        $serverRun = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], ['APP_ENV' => 'messenger']);

        self::assertFileDoesNotExist($testFilePath);

        $serverRun->setTimeout(10);
        $serverRun->start();

        $this->runAsCoroutineAndWait(function () use ($testFile, $testFileContent): void {
            $client = HttpClient::fromDomain('localhost', self::port(), false);
            $this->assertTrue($client->connect(self::connectTimeout(), 1, true));

            $response = $client->send('/message/dispatch', Http::METHOD_POST, [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ], http_build_query([
                'content' => $testFileContent,
                'fileName' => $testFile,
            ]))['response'];

            $this->assertSame(200, $response['statusCode']);
            $this->assertSame('OK', $response['body']);

            Coroutine::sleep($this->coverageEnabled() ? 1 : 3);
        });

        $serverRun->stop();

        self::assertFileExists($testFilePath);
        self::assertSame($testFileContent, file_get_contents($testFilePath));
    }

    private function generateNotExistingCustomTestFile(): string
    {
        return 'tfile-' . $this->generateUniqueHash(4) . '-' . $this->currentUnixTimestamp() . '.txt';
    }
}
