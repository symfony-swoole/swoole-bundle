<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\RequestHandler;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Server\HttpServerConfiguration;
use SwooleBundle\SwooleBundle\Server\RequestHandler\AdvancedStaticFilesServer;

final class AdvancedStaticFilesServerTest extends TestCase
{
    use ProphecyTrait;

    private string $publicDir;

    private HttpServerConfiguration|ObjectProphecy $configurationMock;

    private RequestHandlerDummy $decoratedDummy;

    private AdvancedStaticFilesServer $handler;

    protected function setUp(): void
    {
        $this->publicDir = sys_get_temp_dir() . '/asfs_test_' . uniqid('', true);
        mkdir($this->publicDir, 0o755, true);

        $this->configurationMock = $this->prophesize(HttpServerConfiguration::class);
        $this->configurationMock->hasPublicDir()->willReturn(true);
        $this->configurationMock->getPublicDir()->willReturn($this->publicDir);

        $this->decoratedDummy = new RequestHandlerDummy();

        $this->handler = new AdvancedStaticFilesServer(
            $this->decoratedDummy,
            $this->configurationMock->reveal(),
        );

        $this->handler->boot();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->publicDir);
    }

    public function testPathTraversalIsBlockedAndDelegatesToDecorated(): void
    {
        $maliciousUri = '/../private-file';
        $privateFile = $this->publicDir . '/../private-file';

        $request = $this->makeSwooleRequest('GET', $maliciousUri);
        $response = $this->prophesize(Response::class);

        $response->header(Argument::any(), Argument::any())->shouldNotBeCalled();
        $response->sendfile(Argument::any())->shouldNotBeCalled();

        file_put_contents($privateFile, 'private-data');
        self::assertFileExists($privateFile);

        $this->handler->handle($request, $response->reveal());
        unlink($privateFile);
        self::assertFileDoesNotExist($privateFile);
    }

    public function testNormalFileInsidePublicDirIsServed(): void
    {
        $filename = 'app.js';
        file_put_contents($this->publicDir . '/' . $filename, 'console.log(1)');

        $request = $this->makeSwooleRequest('GET', '/' . $filename);
        $response = $this->prophesize(Response::class);

        $response->header('Content-Type', 'text/javascript')->shouldBeCalled();
        $response->sendfile($this->publicDir . '/' . $filename)->shouldBeCalled();

        $this->handler->handle($request, $response->reveal());
    }

    private function makeSwooleRequest(string $method, string $uri): Request
    {
        /** @var Request $request */
        $request = $this->prophesize(Request::class)->reveal();
        $request->server = [
            'request_method' => $method,
            'request_uri' => $uri,
        ];

        return $request;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
